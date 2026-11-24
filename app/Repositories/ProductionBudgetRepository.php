<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Classes\Billing\PilotShotQuote;
use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Classes\Story\Snapshot;
use App\Data\Billing\GenerationQuoteData;
use App\Enums\GenerationAccountingMode;
use App\Enums\ProjectContentMode;
use App\Enums\ReservationStatus;
use App\Enums\RevisionStatus;
use App\Enums\SeriesAudioMode;
use App\Models\EpisodeRevision;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use App\Models\Shot;
use App\Settings\GeneralSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ProductionBudgetRepository
{
    public function __construct(private GenerationCreditRepository $credits, private VideoGeneratorFactory $generators, private GeneralSettings $settings) {}

    public function prepare(Project $project, ProductionRun $run, ?Series $series): void
    {
        $owner = $project->owner_id;

        if ($owner === null || DB::table('billing_accounts')->where('owner_id', $owner)->lockForUpdate()->value('accounting_mode') !== GenerationAccountingMode::ENFORCED->value) {
            return;
        }

        $latest = $series?->episodes->first();
        $episode = $run->episode ?? ($latest?->accepted_assembly_id === null ? $latest : null);
        $number = $episode->number ?? (($latest->number ?? 0) + 1);

        if ($series?->episode_limit !== null && $number > $series->episode_limit) {
            return;
        }

        $key = $series instanceof Series ? 'episode:' . $run->id . ':' . $number : 'short:' . $run->id;
        $revision = $episode?->currentRevision;
        $revision = $revision?->status === RevisionStatus::APPROVED ? $revision : null;
        /** @var object{id: int, episode_revision_id: ?int}|null $budget */
        $budget = DB::table('generation_reservations')->where('owner_id', $owner)->where('intent_key', $key)->lockForUpdate()->first();

        if ($budget !== null && ($revision === null || $budget->episode_revision_id === $revision->id)) {
            return;
        }

        if ($budget !== null && $budget->episode_revision_id !== null) {
            throw ValidationException::withMessages(['balance' => 'Версия сценария изменилась. Остановите этот запуск и подтвердите резерв новой версии.']);
        }

        $quote = $this->calculate($series, $revision);
        ['seconds' => $seconds, 'shots' => $shots, 'video' => $video] = $quote;
        $id = $budget->id ?? $this->createReservation($project, $run, $key, ['seconds' => $seconds, 'shots' => $shots, 'video' => $video], null);

        if ($budget === null && $seconds > 0) {
            $this->credits->reserve($owner, $id, $key, null, null, $seconds);
        }

        if ($revision === null) {
            return;
        }

        foreach ($shots as $shot => $duration) {
            $shotKey = 'episode-shot:' . $run->id . ':' . $shot;
            $child = $this->createReservation($project, $run, $shotKey, ['seconds' => $duration, 'shot_id' => $shot, 'video' => [...$video, 'duration' => $duration]], $revision->id);
            $this->credits->assign($owner, $id, $child, $shot, $duration);
        }

        $this->credits->settle($owner, $id, ReservationStatus::RELEASED);
        DB::table('generation_reservations')->where('id', $id)->update(['episode_revision_id' => $revision->id, 'status' => 'allocated', 'updated_at' => now('UTC')]);
    }

    /** @return array{seconds: int, shots: array<int, int>, video: array<string, mixed>, revision_id: ?int} */
    private function calculate(?Series $series, ?EpisodeRevision $revision): array
    {
        $duration = $series instanceof Series ? $series->specification['shot_duration_seconds'] ?? 15 : ($this->settings->duration);
        $audio = $series->specification['audio_mode'] ?? SeriesAudioMode::NATIVE->value;
        $provider = $this->generators->primary();
        $caps = $this->generators->make($provider)->capabilities();
        $video = ['provider' => $provider->value, 'model' => $caps->model, 'resolution' => $caps->defaultResolution,
            'aspect_ratio' => $caps->defaultAspectRatio,
            'generate_audio' => $audio === SeriesAudioMode::NATIVE->value && $caps->supportsAudioGeneration, 'duration' => $duration];

        if (PilotShotQuote::seconds($video) === null) {
            throw ValidationException::withMessages(['video' => 'Выберите для производства Seedance Mini 720p, вертикальный кадр и нативный звук.']);
        }

        $shots = $revision instanceof EpisodeRevision ? $this->missingShots($revision, $video) : [];
        $seconds = $revision instanceof EpisodeRevision ? array_sum($shots) : ($series instanceof Series ? $series->specification['episode_duration_seconds'] ?? 15 : ($this->settings->duration));

        if (! is_int($seconds) || $seconds < 0) {
            throw ValidationException::withMessages(['balance' => 'Проверьте длительность эпизода.']);
        }

        return ['seconds' => $seconds, 'shots' => $shots, 'video' => $video, 'revision_id' => $revision?->id];
    }

    public function quoteShot(int $owner, GenerationQuoteData $data): Shot
    {
        $shot = Shot::query()->whereHas('scene.episodeRevision.episode.series.project', fn($query) => $query->whereKey($data->project_id)->where('owner_id', $owner))
            ->when($data->series_id !== null, fn($query) => $query->whereHas('scene.episodeRevision.episode', fn($episode) => $episode->where('series_id', $data->series_id)))
            ->findOrFail($data->shot_id);

        if ($data->revision_id !== null && $shot->scene?->episode_revision_id !== $data->revision_id) {
            throw ValidationException::withMessages(['revision_id' => 'Версия сценария изменилась. Обновите страницу.']);
        }

        return $shot;
    }

    /** @return array{seconds: int, shots: array<int, int>, video: array<string, mixed>, revision_id: ?int} */
    public function quoteProject(int $owner, GenerationQuoteData $data): array
    {
        $project = Project::query()->where('owner_id', $owner)->findOrFail($data->project_id);

        if ($project->isClippingProject() || ($project->content_mode === ProjectContentMode::SERIES && $data->series_id === null)) {
            throw ValidationException::withMessages(['series_id' => 'Выберите сериал для расчёта.']);
        }

        $series = $data->series_id === null ? null : $project->series()->findOrFail($data->series_id);
        $latest = $series?->episodes()->orderByDesc('number')->first();
        $revision = $latest?->accepted_assembly_id === null ? $latest?->currentRevision : null;
        $revision = $revision?->status === RevisionStatus::APPROVED ? $revision : null;

        if ($data->revision_id !== null && $revision?->id !== $data->revision_id) {
            throw ValidationException::withMessages(['revision_id' => 'Версия сценария изменилась. Обновите страницу.']);
        }

        return $this->calculate($series, $revision);
    }

    /**
     * @param array<string, mixed> $video
     * @return array<int, int>
     */
    private function missingShots(EpisodeRevision $revision, array $video): array
    {
        $result = [];
        $revision->load('scenes.shots.prompts');

        foreach ($revision->scenes as $scene) {
            foreach ($scene->shots as $shot) {
                if ($shot->prompts->isNotEmpty()) {
                    continue;
                }

                $seconds = intdiv($shot->duration_ms, 1000);

                if (PilotShotQuote::seconds([...$video, 'duration' => $seconds]) === null || $shot->duration_ms !== $seconds * 1000) {
                    throw ValidationException::withMessages(['video' => 'Длительность кадра не входит в оплачиваемый пресет.']);
                }

                $result[$shot->id] = $seconds;
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $quote */
    private function createReservation(Project $project, ProductionRun $run, string $key, array $quote, ?int $revision): int
    {
        return DB::table('generation_reservations')->insertGetId(['owner_id' => $project->owner_id, 'intent_key' => $key, 'project_id' => $project->id,
            'production_run_id' => $run->id, 'episode_revision_id' => $revision, 'request_hash' => Snapshot::hash($quote), 'quote_snapshot' => json_encode($quote, JSON_THROW_ON_ERROR),
            'accounting_mode' => GenerationAccountingMode::ENFORCED->value, 'status' => 'held', 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
    }

    /** @return array{seconds: int, video: array{model: string, provider: string, resolution: string, aspect_ratio: string, generate_audio: bool, duration: int}}|null */
    public function claimShort(ProductionRun $run): ?array
    {
        return DB::transaction(function () use ($run): ?array {
            /** @var object{id: int, owner_id: int, status: string, quote_snapshot: string}|null $budget */
            $budget = DB::table('generation_reservations')->where('production_run_id', $run->id)->where('intent_key', 'short:' . $run->id)->lockForUpdate()->first();

            if ($budget === null) {
                return null;
            }

            $key = 'short.started.' . $budget->id;
            $event = DB::table('billing_events')->where('event_key', $key)->lockForUpdate()->first(['status']);

            if ($budget->status !== 'held' || ($event !== null && $event->status !== 'retryable')) {
                throw ValidationException::withMessages(['generation' => 'Этот запуск уже обработан. Проверьте его состояние в производстве.']);
            }

            DB::table('billing_events')->upsert([['owner_id' => $budget->owner_id, 'kind' => 'short.started', 'event_key' => $key,
                'status' => 'processed', 'payload' => json_encode(['run' => $run->id], JSON_THROW_ON_ERROR), 'created_at' => now('UTC'), 'updated_at' => now('UTC')]], ['event_key'], ['status', 'updated_at']);

            /** @var array{seconds: int, video: array{model: string, provider: string, resolution: string, aspect_ratio: string, generate_audio: bool, duration: int}} $quote */
            $quote = json_decode($budget->quote_snapshot, true, flags: JSON_THROW_ON_ERROR);

            return $quote;
        }, 3);
    }

    public function attachShort(ProductionRun $run, int $prompt): void
    {
        $reservation = DB::table('generation_reservations')->where('intent_key', 'short:' . $run->id)->where('production_run_id', $run->id)->lockForUpdate()->first(['id', 'status']);

        if ($reservation === null) {
            return;
        }

        if ($reservation->status !== 'held') {
            throw ValidationException::withMessages(['generation' => 'Резерв запуска закрыт.']);
        }

        DB::table('generation_reservations')->where('id', $reservation->id)->update(['prompt_id' => $prompt]);
        DB::table('generation_reservation_parts')->where('reservation_id', $reservation->id)->update(['prompt_id' => $prompt]);
    }

    public function deferSchedule(Project $project, string $requestKey, string $message): void
    {
        $at = now('UTC');
        DB::table('billing_events')->upsert([['owner_id' => $project->owner_id, 'event_key' => 'schedule.balance.' . $project->id,
            'kind' => 'schedule.balance', 'status' => 'waiting', 'payload' => json_encode(['project_id' => $project->id, 'request_key' => $requestKey, 'message' => $message], JSON_THROW_ON_ERROR),
            'next_check_at' => $at->copy()->addDay(), 'created_at' => $at, 'updated_at' => $at]], ['event_key'], ['status', 'payload', 'next_check_at', 'updated_at']);
    }

    public function isDeferred(Project $project): bool
    {
        return DB::table('billing_events')->where('event_key', 'schedule.balance.' . $project->id)->where('status', 'waiting')->where('next_check_at', '>', now('UTC'))->exists();
    }

    public function shortPreparationFailed(ProductionRun $run, bool $retryable): void
    {
        DB::transaction(function () use ($run, $retryable): void {
            /** @var object{id: int, owner_id: int, prompt_id: ?int}|null $budget */
            $budget = DB::table('generation_reservations')->where('intent_key', 'short:' . $run->id)->where('status', 'held')->lockForUpdate()->first();

            if ($budget === null) {
                return;
            }

            $event = DB::table('billing_events')->where('event_key', 'short.started.' . $budget->id);

            if ($retryable && $budget->prompt_id === null) {
                $event->update(['status' => 'retryable', 'updated_at' => now('UTC')]);

                return;
            }

            DB::table('billing_accounts')->where('owner_id', $budget->owner_id)->lockForUpdate()->firstOrFail();
            $this->credits->settle($budget->owner_id, $budget->id, ReservationStatus::RELEASED);
            DB::table('generation_reservations')->where('id', $budget->id)->update(['status' => 'released', 'updated_at' => now('UTC')]);
        }, 3);
    }

    public function releaseUnused(ProductionRun $run): void
    {
        /** @var Collection<int, object{id: int, owner_id: int}> $reservations */
        $reservations = DB::table('generation_reservations')->where('production_run_id', $run->id)->where('accounting_mode', GenerationAccountingMode::ENFORCED->value)
            ->whereNull('prompt_id')->where('status', 'held')->orderBy('id')->lockForUpdate()->get(['id', 'owner_id']);

        foreach ($reservations as $reservation) {
            DB::table('billing_accounts')->where('owner_id', $reservation->owner_id)->lockForUpdate()->firstOrFail();
            $this->credits->settle($reservation->owner_id, $reservation->id, ReservationStatus::RELEASED);
            DB::table('generation_reservations')->where('id', $reservation->id)->update(['status' => 'released', 'updated_at' => now('UTC')]);
        }
    }
}
