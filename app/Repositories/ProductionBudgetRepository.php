<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Classes\Story\Snapshot;
use App\Data\Billing\GenerationQuoteData;
use App\Enums\GenerationAccountingMode;
use App\Enums\ReservationStatus;
use App\Models\EpisodeRevision;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use App\Models\Shot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ProductionBudgetRepository
{
    public function __construct(private GenerationCreditRepository $credits) {}

    public function lockEnforcedAccount(int $owner): bool
    {
        return DB::table('billing_accounts')->where('owner_id', $owner)->lockForUpdate()->value('accounting_mode') === GenerationAccountingMode::ENFORCED->value;
    }

    /** @return object{id: int, episode_revision_id: ?int}|null */
    public function lockBudgetForIntent(int $owner, string $key): ?object
    {
        /** @var object{id: int, episode_revision_id: ?int}|null */
        return DB::table('generation_reservations')->where('owner_id', $owner)->where('intent_key', $key)->lockForUpdate()->first();
    }

    public function markAllocated(int $id, int $revisionId): void
    {
        DB::table('generation_reservations')->where('id', $id)->update([
            'episode_revision_id' => $revisionId,
            'status' => 'allocated',
            'updated_at' => now('UTC'),
        ]);
    }

    /** @return array<int, int> */
    public function unproducedShotDurations(EpisodeRevision $revision): array
    {
        /** @var array<int, int> */
        return Shot::query()->join('scenes', 'scenes.id', '=', 'shots.scene_id')
            ->where('scenes.episode_revision_id', $revision->id)->doesntHave('prompts')
            ->orderBy('scenes.position')->orderBy('shots.position')->pluck('shots.duration_ms', 'shots.id')->all();
    }

    public function quoteShot(int $owner, GenerationQuoteData $data): Shot
    {
        return Shot::query()->with('scene')->whereHas('scene.episodeRevision.episode.series.project', fn($query) => $query->whereKey($data->project_id)->where('owner_id', $owner))
            ->when($data->series_id !== null, fn($query) => $query->whereHas('scene.episodeRevision.episode', fn($episode) => $episode->where('series_id', $data->series_id)))
            ->findOrFail($data->shot_id);
    }

    public function ownedProject(int $owner, int $project): Project
    {
        return Project::query()->where('owner_id', $owner)->findOrFail($project);
    }

    public function projectSeries(Project $project, int $series): Series
    {
        return $project->series()->findOrFail($series);
    }

    public function latestUnacceptedRevision(?Series $series): ?EpisodeRevision
    {
        $latest = $series?->episodes()->orderByDesc('number')->first();

        return $latest?->accepted_assembly_id === null ? $latest?->currentRevision : null;
    }

    /** @param array<string, mixed> $quote */
    public function createReservation(Project $project, ProductionRun $run, string $key, array $quote, ?int $revision): int
    {
        return DB::table('generation_reservations')->insertGetId([
            'owner_id' => $project->owner_id,
            'intent_key' => $key,
            'project_id' => $project->id,
            'production_run_id' => $run->id,
            'episode_revision_id' => $revision,
            'request_hash' => Snapshot::hash($quote),
            'quote_snapshot' => json_encode($quote, JSON_THROW_ON_ERROR),
            'accounting_mode' => GenerationAccountingMode::ENFORCED->value,
            'status' => 'held',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
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

            DB::table('billing_events')->upsert([[
                'owner_id' => $budget->owner_id,
                'kind' => 'short.started',
                'event_key' => $key,
                'status' => 'processed',
                'payload' => json_encode(['run' => $run->id], JSON_THROW_ON_ERROR),
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]], ['event_key'], ['status', 'updated_at']);

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
        DB::table('billing_events')->upsert([[
            'owner_id' => $project->owner_id,
            'event_key' => 'schedule.balance.' . $project->id,
            'kind' => 'schedule.balance',
            'status' => 'waiting',
            'payload' => json_encode([
                'project_id' => $project->id,
                'request_key' => $requestKey,
                'message' => $message,
            ], JSON_THROW_ON_ERROR),
            'next_check_at' => $at->copy()->addDay(),
            'created_at' => $at,
            'updated_at' => $at,
        ]], ['event_key'], [
            'status',
            'payload',
            'next_check_at',
            'updated_at',
        ]);
    }

    public function isDeferred(Project $project): bool
    {
        return DB::table('billing_events')->where('event_key', 'schedule.balance.' . $project->id)->where('status', 'waiting')->where('next_check_at', '>', now('UTC'))->exists();
    }

    public function shortPreparationFailed(ProductionRun $run, bool $retryable): void
    {
        DB::transaction(function () use ($run, $retryable): void {
            $reservation = DB::table('generation_reservations')->where('production_run_id', $run->id)->where('intent_key', 'short:' . $run->id);
            $owner = $reservation->value('owner_id');

            if ($owner === null) {
                return;
            }

            DB::table('billing_accounts')->where('owner_id', $owner)->lockForUpdate()->firstOrFail();
            /** @var object{id: int, owner_id: int, prompt_id: ?int}|null $budget */
            $budget = $reservation->where('status', ReservationStatus::HELD->value)->lockForUpdate()->first();

            if ($budget === null) {
                return;
            }

            $event = DB::table('billing_events')->where('event_key', 'short.started.' . $budget->id);

            if ($retryable && $budget->prompt_id === null) {
                $event->update(['status' => 'retryable', 'updated_at' => now('UTC')]);

                return;
            }

            $this->credits->settle($budget->owner_id, $budget->id, ReservationStatus::RELEASED);
            DB::table('generation_reservations')->where('id', $budget->id)->update(['status' => 'released', 'updated_at' => now('UTC')]);
        }, 3);
    }

    public function releaseUnused(ProductionRun $run): void
    {
        /** @var Collection<int, object{id: int, owner_id: int}> $reservations */
        $reservations = DB::table('generation_reservations')->where('production_run_id', $run->id)->where('accounting_mode', GenerationAccountingMode::ENFORCED->value)
            ->whereNull('prompt_id')->where('status', ReservationStatus::HELD->value)->orderBy('owner_id')->orderBy('id')->get(['id', 'owner_id']);

        foreach ($reservations as $reservation) {
            DB::table('billing_accounts')->where('owner_id', $reservation->owner_id)->lockForUpdate()->firstOrFail();
            $released = DB::table('generation_reservations')->where('id', $reservation->id)->whereNull('prompt_id')
                ->where('status', ReservationStatus::HELD->value)->update(['status' => ReservationStatus::RELEASED->value, 'updated_at' => now('UTC')]);

            if ($released > 0) {
                $this->credits->settle($reservation->owner_id, $reservation->id, ReservationStatus::RELEASED);
            }
        }
    }
}
