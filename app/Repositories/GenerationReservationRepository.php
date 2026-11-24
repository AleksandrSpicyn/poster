<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Classes\Billing\PilotShotQuote;
use App\Enums\GenerationAccountingMode;
use App\Enums\GenerationOperationStatus;
use App\Enums\ReservationStatus;
use App\Models\Project;
use App\Models\Prompt;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class GenerationReservationRepository
{
    public function __construct(private GenerationCreditRepository $credits) {}

    public function reserveTake(Project $project, Prompt $take, ?int $runId): void
    {
        $snapshot = $take->input_snapshot;
        $video = $snapshot['video'] ?? null;
        $seconds = PilotShotQuote::seconds($video);

        if ($project->owner_id === null) {
            return;
        }

        $mode = DB::table('billing_accounts')->where('owner_id', $project->owner_id)->lockForUpdate()->value('accounting_mode');

        if ($mode === GenerationAccountingMode::ENFORCED->value && $seconds === null) {
            throw ValidationException::withMessages(['video' => 'Пакет покрывает вертикальный дубль Seedance Mini 480p или 720p с нативным звуком длительностью от 4 до 15 секунд.']);
        }

        if ($seconds === null || ! in_array($mode, [GenerationAccountingMode::SHADOW->value, GenerationAccountingMode::ENFORCED->value], true)) {
            return;
        }

        if ($mode === GenerationAccountingMode::ENFORCED->value && $take->shot_id !== null) {
            /** @var object{id: int, quote_snapshot: string}|null $slot */
            $slot = DB::table('generation_reservations as r')->join('generation_reservation_parts as p', 'p.reservation_id', '=', 'r.id')
                ->where('r.owner_id', $project->owner_id)->where('r.episode_revision_id', $snapshot['episode_revision_id'] ?? null)
                ->where('p.shot_id', $take->shot_id)->whereNull('r.prompt_id')->where('r.status', 'held')->where('r.intent_key', 'like', 'episode-shot:%')->lockForUpdate()->first(['r.id', 'r.quote_snapshot']);

            if ($slot !== null) {
                $quote = json_decode($slot->quote_snapshot, true, flags: JSON_THROW_ON_ERROR);

                if (data_get($quote, 'seconds') !== $seconds) {
                    throw ValidationException::withMessages(['balance' => 'Длительность кадра изменилась после резервирования.']);
                }

                DB::table('generation_reservations')->where('id', $slot->id)->update([
                    'prompt_id' => $take->id,
                    'request_hash' => $take->input_hash,
                    'updated_at' => now('UTC'),
                ]);
                DB::table('generation_reservation_parts')->where('reservation_id', $slot->id)->update(['prompt_id' => $take->id, 'updated_at' => now('UTC')]);

                return;
            }
        }

        $now = now('UTC');
        $id = DB::table('generation_reservations')->insertGetId([
            'owner_id' => $project->owner_id,
            'intent_key' => 'shot-take:' . $take->id,
            'request_hash' => $take->input_hash,
            'project_id' => $project->id,
            'prompt_id' => $take->id,
            'production_run_id' => $runId,
            'episode_revision_id' => $snapshot['episode_revision_id'] ?? null,
            'accounting_mode' => $mode,
            'status' => ReservationStatus::HELD->value,
            'quote_snapshot' => json_encode([
                'seconds' => $seconds,
                'shot_id' => $take->shot_id,
                'video' => $video,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($mode === GenerationAccountingMode::ENFORCED->value) {
            $this->credits->reserve($project->owner_id, $id, 'shot-take:' . $take->id, $take->shot_id, $take->id, $seconds);
        }
    }

    /** @param list<int> $takeIds */
    public function transitionTakeReservations(array $takeIds, ReservationStatus $to): void
    {
        if ($takeIds === []) {
            return;
        }

        $from = $to === ReservationStatus::HELD ? ReservationStatus::RELEASED : ReservationStatus::HELD;
        /** @var Collection<int, object{id: int, owner_id: int, prompt_id: int, quote_snapshot: string}> $reservations */
        $reservations = DB::table('generation_reservations')->whereIn('prompt_id', $takeIds)->where('accounting_mode', GenerationAccountingMode::ENFORCED->value)
            ->where('status', $from->value)->orderBy('owner_id')->orderBy('id')->get();

        foreach ($reservations as $reservation) {
            DB::table('billing_accounts')->where('owner_id', $reservation->owner_id)->lockForUpdate()->firstOrFail();
            $changed = DB::table('generation_reservations')->where('id', $reservation->id)->where('status', $from->value)
                ->update(['status' => $to->value, 'updated_at' => now('UTC')]);

            if ($changed === 0) {
                continue;
            }

            if ($to === ReservationStatus::HELD) {
                /** @var array{shot_id: ?int, seconds: int} $quote */
                $quote = json_decode($reservation->quote_snapshot, true, flags: JSON_THROW_ON_ERROR);
                $this->credits->reserve($reservation->owner_id, $reservation->id, 'retry:' . Str::uuid(), $quote['shot_id'], $reservation->prompt_id, $quote['seconds']);
            } else {
                $this->credits->settle($reservation->owner_id, $reservation->id, $to);
            }
        }

        DB::table('generation_reservations')->whereIn('prompt_id', $takeIds)->where('intent_key', 'like', 'shot-take:%')->where('accounting_mode', GenerationAccountingMode::SHADOW->value)
            ->where('status', $from->value)->update(['status' => $to->value, 'updated_at' => now('UTC')]);
    }

    public function releaseUndeliverable(int $promptId, int $operator, string $reason): void
    {
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainException('Укажите основание длиной до 500 символов.');
        }

        DB::transaction(function () use ($promptId, $operator, $reason): void {
            $prompt = Prompt::query()->lockForUpdate()->find($promptId);

            if ($prompt === null || $prompt->generated || $prompt->hasMedia() || $prompt->hasMedia(Prompt::SHOT_VIDEO)) {
                throw new DomainException('Проверьте существование работы и прикреплённый файл.');
            }

            $paidReservation = DB::table('generation_reservations')->where('prompt_id', $promptId)->where('accounting_mode', GenerationAccountingMode::ENFORCED->value);
            DB::table('billing_accounts')->whereIn('owner_id', (clone $paidReservation)->select('owner_id'))->orderBy('owner_id')->lockForUpdate()->get(['owner_id']);
            /** @var object{id: int, owner_id: int, production_run_id: ?int, status: string}|null $reservation */
            $reservation = $paidReservation->lockForUpdate()->first();

            if ($reservation === null) {
                throw new DomainException('Платный резерв не найден.');
            }

            if ($reservation->status === ReservationStatus::RELEASED->value) {
                return;
            }

            $running = $prompt->shot_id === null
                ? DB::table('production_runs')->where('id', $reservation->production_run_id)->whereNotIn('status', ['needs_attention', 'stopped'])->exists()
                : DB::table('generation_operations')->where('prompt_id', $promptId)->whereIn('status', array_column(GenerationOperationStatus::active(), 'value'))->exists();

            if ($running || $reservation->status !== 'held') {
                throw new DomainException('Сначала завершите обработку или разберите неизвестную внешнюю отправку.');
            }

            $this->transitionTakeReservations([$promptId], ReservationStatus::RELEASED);
            DB::table('billing_events')->insert([
                'owner_id' => $reservation->owner_id,
                'event_key' => 'hold.released.' . Str::uuid(),
                'kind' => 'hold.released',
                'status' => 'processed',
                'payload' => json_encode([
                    'reservation' => $reservation->id,
                    'operator' => $operator,
                    'reason' => $reason,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
        }, 3);
    }

    public function assertDeliverable(int $prompt): void
    {
        if (DB::table('generation_reservations')->where('prompt_id', $prompt)->where('accounting_mode', GenerationAccountingMode::ENFORCED->value)->where('status', 'released')->exists()) {
            throw ValidationException::withMessages(['balance' => 'Резерв этой генерации уже освобождён. Требуется повторная проверка перед выдачей.']);
        }
    }

    public function quotedSeconds(int $prompt): ?int
    {
        $json = DB::table('generation_reservations')->where('prompt_id', $prompt)->where('accounting_mode', GenerationAccountingMode::ENFORCED->value)->value('quote_snapshot');

        if (! is_string($json)) {
            return null;
        }

        /** @var array{seconds: int} $quote */
        $quote = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $quote['seconds'];
    }
}
