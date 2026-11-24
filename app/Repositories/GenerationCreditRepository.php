<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\GenerationCreditBucket;
use App\Enums\ReservationStatus;
use App\Events\WorkspaceUpdated;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GenerationCreditRepository
{
    /** Called inside the generation transaction with the owner account locked. */
    public function reserve(int $owner, int $reservation, string $key, ?int $shot, ?int $prompt, int $seconds): void
    {
        $at = now('UTC');
        /** @var Collection<int, object{id: int, available_seconds: int}> $grants */
        $grants = DB::table('generation_grants')->where('owner_id', $owner)->where('starts_at', '<=', $at)
            ->where(fn(Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $at))
            ->where('available_seconds', '>', 0)->orderByRaw('case when ends_at is null then 1 else 0 end')->orderBy('ends_at')->orderBy('id')
            ->lockForUpdate()->get(['id', 'available_seconds']);
        $available = $grants->reduce(static fn(int $sum, object $grant): int => $sum + $grant->available_seconds, 0);

        if ($seconds > $available) {
            throw ValidationException::withMessages(['balance' => sprintf('Для генерации нужно %d с. Доступно %d с. Не хватает %d с.', $seconds, $available, $seconds - $available)]);
        }

        foreach ($grants as $grant) {
            $quantity = min($grant->available_seconds, $seconds);
            $part = DB::table('generation_reservation_parts')->insertGetId([
                'owner_id' => $owner,
                'reservation_id' => $reservation,
                'take_key' => $key,
                'grant_id' => $grant->id,
                'shot_id' => $shot,
                'prompt_id' => $prompt,
                'seconds' => $quantity,
                'status' => ReservationStatus::HELD->value,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $this->transfer($owner, $grant->id, $part, $quantity, GenerationCreditBucket::AVAILABLE, GenerationCreditBucket::HELD, 'reserve');
            $seconds -= $quantity;

            if ($seconds === 0) {
                break;
            }
        }
    }

    /** Assign a slice of an already held episode budget to an individual shot. */
    public function assign(int $owner, int $budget, int $reservation, int $shot, int $seconds): void
    {
        /** @var Collection<int, object{id: int, grant_id: int, seconds: int}> $parts */
        $parts = DB::table('generation_reservation_parts')->where('reservation_id', $budget)->where('status', 'held')->where('seconds', '>', 0)->orderBy('id')->lockForUpdate()->get();
        $at = now('UTC');

        foreach ($parts as $part) {
            $quantity = min($seconds, $part->seconds);
            $child = DB::table('generation_reservation_parts')->insertGetId([
                'owner_id' => $owner,
                'reservation_id' => $reservation,
                'grant_id' => $part->grant_id,
                'take_key' => 'shot:' . $shot,
                'shot_id' => $shot,
                'seconds' => $quantity,
                'status' => 'held',
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            DB::table('generation_reservation_parts')->where('id', $part->id)->update([
                'seconds' => $part->seconds - $quantity,
                'updated_at' => $at,
                'status' => $part->seconds === $quantity ? 'allocated' : 'held',
            ]);

            foreach ([$part->id => -$quantity, $child => $quantity] as $id => $delta) {
                DB::table('generation_credit_entries')->insert([
                    'owner_id' => $owner,
                    'grant_id' => $part->grant_id,
                    'part_id' => $id,
                    'kind' => 'allocate',
                    'held_delta' => $delta,
                    'event_key' => 'allocate.' . $child . '.' . $id,
                    'created_at' => $at,
                ]);
            }

            $seconds -= $quantity;

            if ($seconds === 0) {
                return;
            }
        }

        throw ValidationException::withMessages(['balance' => 'Длительность кадров превышает согласованный резерв эпизода. Проверьте сценарий перед новым запуском.']);
    }

    public function settle(int $owner, int $reservation, ReservationStatus $to): void
    {
        /** @var Collection<int, object{id: int, grant_id: int, seconds: int}> $parts */
        $parts = DB::table('generation_reservation_parts')->where('owner_id', $owner)->where('reservation_id', $reservation)
            ->where('status', ReservationStatus::HELD->value)->orderBy('grant_id')->lockForUpdate()->get([
                'id',
                'grant_id',
                'seconds',
            ]);

        foreach ($parts as $part) {
            $expired = DB::table('generation_grants')->where('id', $part->grant_id)->where('ends_at', '<=', now('UTC'))->exists();
            $target = $to === ReservationStatus::CONSUMED ? GenerationCreditBucket::CONSUMED : ($expired ? GenerationCreditBucket::EXPIRED : GenerationCreditBucket::AVAILABLE);
            $this->transfer($owner, $part->grant_id, $part->id, $part->seconds, GenerationCreditBucket::HELD, $target, $to === ReservationStatus::CONSUMED ? 'consume' : 'release');
            DB::table('generation_reservation_parts')->where('id', $part->id)->update(['status' => $to->value, 'updated_at' => now('UTC')]);
        }
    }

    public function expire(): int
    {
        $count = 0;
        DB::table('generation_grants')->where('ends_at', '<=', now('UTC'))->where('available_seconds', '>', 0)
            ->chunkById(100, function ($rows) use (&$count): void {
                /** @var object{id: int, owner_id: int} $row */
                foreach ($rows as $row) {
                    $count += DB::transaction(function () use ($row): int {
                        DB::table('billing_accounts')->where('owner_id', $row->owner_id)->lockForUpdate()->firstOrFail();
                        /** @var object{available_seconds: int} $grant */
                        $grant = DB::table('generation_grants')->where('id', $row->id)->lockForUpdate()->firstOrFail();

                        if ($grant->available_seconds === 0) {
                            return 0;
                        }

                        $this->transfer($row->owner_id, $row->id, null, $grant->available_seconds, GenerationCreditBucket::AVAILABLE, GenerationCreditBucket::EXPIRED, 'expire');

                        return 1;
                    }, 3);
                }
            }, 'id');

        return $count;
    }

    /** The caller holds the owner and grant locks in the surrounding transaction. */
    public function transfer(int $owner, int $grant, ?int $part, int $seconds, GenerationCreditBucket $from, GenerationCreditBucket $to, string $kind, ?string $event = null, ?string $reason = null): void
    {
        DB::table('generation_grants')->where('id', $grant)->incrementEach([$from->value . '_seconds' => -$seconds, $to->value . '_seconds' => $seconds], ['updated_at' => now('UTC')]);
        DB::table('generation_credit_entries')->insert([
            'owner_id' => $owner,
            'grant_id' => $grant,
            'part_id' => $part,
            'kind' => $kind,
            'reason' => $reason,
            'event_key' => $event ?? (string) Str::uuid(),
            $from->value . '_delta' => -$seconds,
            $to->value . '_delta' => $seconds,
            'created_at' => now('UTC'),
        ]);
        WorkspaceUpdated::forBalance($owner);
    }
}
