<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\GenerationAccountingMode;
use App\Enums\ProductionStatus;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class BillingAccountingRepository
{
    public function configureShadowUsage(int $owner, bool $enable): void
    {
        $mode = $enable ? GenerationAccountingMode::SHADOW : GenerationAccountingMode::LEGACY;
        DB::transaction(function () use ($owner, $mode): void {
            if (DB::table('users')->where('id', $owner)->lockForUpdate()->first(['id']) === null) {
                throw new DomainException('Владелец не найден.');
            }

            $accounts = DB::table('billing_accounts')->where('owner_id', $owner);
            $account = $accounts->lockForUpdate()->first(['accounting_mode']);

            if ($account === null) {
                if ($mode === GenerationAccountingMode::SHADOW) {
                    $accounts->insert([
                        'owner_id' => $owner,
                        'accounting_mode' => $mode->value,
                        'created_at' => now('UTC'),
                        'updated_at' => now('UTC'),
                    ]);
                }

                return;
            }

            if (! in_array($account->accounting_mode, [GenerationAccountingMode::LEGACY->value, GenerationAccountingMode::SHADOW->value], true)) {
                throw new DomainException('Этим способом можно переключать только наблюдение за дублями.');
            }

            $accounts->update(['accounting_mode' => $mode->value, 'updated_at' => now('UTC')]);
        }, 3);
    }

    public function configurePilot(int $owner, ?GenerationAccountingMode $mode, ?bool $sales, ?bool $automatic): void
    {
        DB::transaction(function () use ($owner, $mode, $sales, $automatic): void {
            if (DB::table('users')->where('id', $owner)->lockForUpdate()->first(['id']) === null) {
                throw new DomainException('Владелец не найден.');
            }

            $projectIds = DB::table('projects')->where('owner_id', $owner)->orderBy('id')->lockForUpdate()->pluck('id');
            $accounts = DB::table('billing_accounts')->where('owner_id', $owner);
            $account = $accounts->lockForUpdate()->first();

            if ($mode instanceof GenerationAccountingMode && $mode->value !== ($account->accounting_mode ?? GenerationAccountingMode::LEGACY->value)) {
                $busy = DB::table('production_runs')->whereIn('project_id', $projectIds)->whereIn('status', ProductionStatus::blockingValues())->exists()
                    || DB::table('generation_operations as op')->join('prompts as p', 'p.id', '=', 'op.prompt_id')->whereIn('p.project_id', $projectIds)
                        ->whereIn('op.status', [
                            'planned',
                            'submitting',
                            'submitted',
                            'running',
                            'submission_unknown',
                        ])->exists()
                    || DB::table('generation_reservations')->where('owner_id', $owner)->where('status', 'held')->exists();

                if ($busy) {
                    throw new DomainException('Сначала завершите или разберите открытые запуски и резервы владельца.');
                }
            }

            $values = array_filter([
                'accounting_mode' => $mode?->value,
                'sales_enabled' => $sales,
                'automatic_payments_enabled' => $automatic,
            ], static fn(mixed $value): bool => $value !== null);

            if ($values === []) {
                return;
            }

            $at = now('UTC');

            if ($account === null) {
                $accounts->insert([
                    ...$values,
                    'owner_id' => $owner,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            } else {
                $accounts->update([...$values, 'updated_at' => $at]);
            }

            DB::table('billing_events')->insert([
                'owner_id' => $owner,
                'event_key' => (string) Str::uuid(),
                'kind' => 'pilot.configured',
                'status' => 'processed',
                'payload' => json_encode($values, JSON_THROW_ON_ERROR),
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }, 3);
    }

    public function storedMode(int $owner): ?string
    {
        /** @var object{accounting_mode: ?string}|null $account */
        $account = DB::table('users as u')->leftJoin('billing_accounts as b', 'b.owner_id', '=', 'u.id')
            ->where('u.id', $owner)->first(['b.accounting_mode']);

        return $account === null ? null : ($account->accounting_mode ?? GenerationAccountingMode::LEGACY->value);
    }

    /** @return Collection<int, stdClass> */
    public function shadowTotals(int $owner): Collection
    {
        $seconds = DB::getDriverName() === 'sqlite' ? "json_extract(quote_snapshot, '$.seconds')" : "cast(json_unquote(json_extract(quote_snapshot, '$.seconds')) as unsigned)";

        return DB::table('generation_reservations')->where('owner_id', $owner)->where('accounting_mode', GenerationAccountingMode::SHADOW->value)
            ->where('intent_key', 'like', 'shot-take:%')->groupBy('status')->orderBy('status')
            ->select('status')->selectRaw('count(*) as takes, sum(' . $seconds . ') as seconds')->get();
    }
}
