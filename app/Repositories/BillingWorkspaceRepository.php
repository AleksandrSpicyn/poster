<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Billing\NextBillingPlanData;
use App\Data\Billing\RenewalConsentData;
use App\Enums\BillingProduct;
use App\Enums\GenerationAccountingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

final class BillingWorkspaceRepository
{
    public function account(int $owner): ?stdClass
    {
        return DB::table('billing_accounts')->where('owner_id', $owner)->select(['sales_enabled', 'renewal_consent', 'automatic_payments_enabled', 'current_product', 'next_product', 'renews_at', 'payment_method_last_four'])
            ->addSelect(['retry_at' => DB::table('billing_orders as o')->select('o.retry_at')->whereColumn('o.owner_id', 'billing_accounts.owner_id')->whereColumn('o.renewal_due_at', 'billing_accounts.renews_at')->whereNotNull('o.retry_at')->latest('o.id')->limit(1)])
            ->selectRaw('case when renews_at > ? and renews_at <= ? then 1 else 0 end as reminder_due', [now('UTC'), now('UTC')->addDays(3)])
            ->selectRaw('case when payment_method_id is null then 0 else 1 end as has_card')->first();
    }

    public function setNextPlan(int $owner, NextBillingPlanData $data): void
    {
        DB::transaction(function () use ($owner, $data): void {
            $product = $data->product;
            $account = DB::table('billing_accounts')->where('owner_id', $owner);
            $current = $account->lockForUpdate()->first();

            if ($current === null || ! $this->activeMonth($owner)) {
                throw ValidationException::withMessages(['product' => 'Выбор следующего тарифа доступен в оплаченном месяце.']);
            }

            if ($current->renewal_consent && ($current->next_product ?? $current->current_product) !== ($product->value ?? $current->current_product)) {
                /** @var object{current_product: string, consent_revision: int} $current */
                $next = $product ?? BillingProduct::from($current->current_product);

                if ($data->catalog_version !== BillingProduct::CATALOG_VERSION || $data->amount_kopecks !== $next->amountKopecks()) {
                    throw ValidationException::withMessages(['product' => 'Условия продления изменились. Обновите страницу перед подтверждением.']);
                }

                $revision = $current->consent_revision + 1;
                $account->update(['consent_revision' => $revision]);
                DB::table('billing_consents')->insert(['owner_id' => $owner, 'actor_id' => $owner, 'revision' => $revision, 'action' => 'change_plan',
                    'product_code' => $next->value, 'amount_kopecks' => $next->amountKopecks(), 'terms_version' => BillingProduct::CATALOG_VERSION, 'created_at' => now('UTC')]);
            }

            $account->update(['next_product' => $current->current_product === $product?->value ? null : $product?->value, 'updated_at' => now('UTC')]);
        }, 3);
    }

    public function hasMandate(int $owner, int $revision, BillingProduct $product): bool
    {
        return DB::table('billing_consents')->where('owner_id', $owner)->where('revision', $revision)->whereIn('action', ['enable', 'change_plan'])
            ->where('product_code', $product->value)->where('amount_kopecks', $product->amountKopecks())->where('terms_version', BillingProduct::CATALOG_VERSION)->exists();
    }

    public function setConsent(int $owner, RenewalConsentData $data): void
    {
        DB::transaction(function () use ($owner, $data): void {
            $accounts = DB::table('billing_accounts')->where('owner_id', $owner);
            /** @var object{renewal_consent: bool, consent_revision: int, automatic_payments_enabled: bool, current_product: ?string, next_product: ?string, payment_method_id: ?string}|null $account */
            $account = $accounts->lockForUpdate()->first();

            if ($account === null || ($data->enabled && (! $account->automatic_payments_enabled || $account->payment_method_id === null || ! $this->activeMonth($owner)
                || ($account->next_product ?? $account->current_product) !== $data->product->value))) {
                throw ValidationException::withMessages(['enabled' => 'Проверьте сохранённую карту и тариф для продления.']);
            }

            if ((bool) $account->renewal_consent === $data->enabled) {
                return;
            }

            $revision = $account->consent_revision + 1;
            $accounts->update(['renewal_consent' => $data->enabled, 'consent_revision' => $revision, 'updated_at' => now('UTC')]);
            DB::table('billing_consents')->insert(['owner_id' => $owner, 'actor_id' => $owner, 'revision' => $revision, 'action' => $data->enabled ? 'enable' : 'disable',
                'terms_version' => $data->catalog_version, 'product_code' => $data->product->value, 'amount_kopecks' => $data->product->amountKopecks(), 'created_at' => now('UTC')]);
        }, 3);
    }

    public function detachCard(int $owner): void
    {
        DB::transaction(function () use ($owner): void {
            $account = DB::table('billing_accounts')->where('owner_id', $owner);
            /** @var object{payment_method_id: ?string, consent_revision: int, renewal_consent: int|bool}|null $current */
            $current = $account->lockForUpdate()->first();

            if ($current === null || ($current->payment_method_id === null && ! $current->renewal_consent)) {
                return;
            }

            $now = now('UTC');
            $revision = $current->consent_revision + 1;
            $account->update(['renewal_consent' => false, 'payment_method_id' => null, 'payment_method_last_four' => null, 'payment_method_provider' => null,
                'payment_method_test_mode' => null, 'payment_method_shop' => null, 'consent_revision' => $revision, 'updated_at' => $now]);
            DB::table('billing_consents')->insert(['owner_id' => $owner, 'actor_id' => $owner, 'revision' => $revision,
                'action' => 'detach', 'terms_version' => BillingProduct::CATALOG_VERSION, 'created_at' => $now]);
        }, 3);
    }

    /** @return Collection<int, stdClass> */
    public function usage(int $owner, ?int $shot, ?int $run): Collection
    {
        return DB::table('generation_credit_entries as e')->join('generation_reservation_parts as p', 'p.id', '=', 'e.part_id')
            ->join('generation_reservations as r', 'r.id', '=', 'p.reservation_id')->join('generation_grants as g', 'g.id', '=', 'p.grant_id')
            ->join('billing_orders as o', 'o.id', '=', 'g.order_id')->where('e.owner_id', $owner)->where('r.accounting_mode', '!=', GenerationAccountingMode::SHADOW->value)
            ->when($shot !== null, fn(Builder $q) => $q->where('p.shot_id', $shot))
            ->when($run !== null, fn(Builder $q) => $q->where('r.production_run_id', $run))
            ->groupBy('g.id', 'o.id', 'o.product_code')->orderBy('g.id')->select(['o.id as order_id', 'o.product_code'])
            ->selectRaw('sum(e.held_delta) as held, sum(e.consumed_delta) as consumed')->get()
            ->each(static function (stdClass $row): void {
                /** @var string $code */
                $code = $row->product_code;
                $row->product_label = BillingProduct::tryFrom($code)?->label() ?? $code;
            });
    }

    /** @return array{estimate: ?int, shots: list<array{shot_id: int, prompt_id: ?int, seconds: int, status: string}>} */
    public function runQuote(int $owner, int $run): array
    {
        /** @var Collection<int, object{intent_key: string, quote_snapshot: string, status: string, prompt_id: ?int}> $rows */
        $rows = DB::table('generation_reservations')->where('owner_id', $owner)->where('production_run_id', $run)
            ->where('accounting_mode', GenerationAccountingMode::ENFORCED->value)->orderBy('id')->get(['intent_key', 'quote_snapshot', 'status', 'prompt_id']);
        $estimate = null;
        $shots = [];

        foreach ($rows as $row) {
            /** @var array{seconds?: int, shot_id?: int} $quote */
            $quote = json_decode($row->quote_snapshot, true, flags: JSON_THROW_ON_ERROR);

            if (! isset($quote['seconds'])) {
                continue;
            }

            if (str_starts_with($row->intent_key, 'episode:') || str_starts_with($row->intent_key, 'short:')) {
                $estimate = ($estimate ?? 0) + $quote['seconds'];
            } elseif (isset($quote['shot_id'])) {
                $shots[] = ['shot_id' => $quote['shot_id'], 'prompt_id' => $row->prompt_id, 'seconds' => $quote['seconds'], 'status' => $row->status];
            }
        }

        return ['estimate' => $estimate, 'shots' => $shots];
    }

    public function hasOtherModePayment(int $owner, bool $test): bool
    {
        return DB::table('billing_payments as p')->join('billing_orders as o', 'o.id', '=', 'p.order_id')
            ->where('o.owner_id', $owner)->where('p.test_mode', ! $test)->whereIn('p.status', ['succeeded', 'pending', 'unknown'])->exists();
    }

    public function hasPurchased(int $owner): bool
    {
        return DB::table('generation_grants')->where('owner_id', $owner)->exists()
            || DB::table('billing_orders')->where('owner_id', $owner)->whereIn('status', ['paid', 'succeeded', 'refunded'])->exists();
    }

    public function activeMonth(int $owner): bool
    {
        return $this->grants($owner)->where('o.status', '!=', 'refunded')->whereNotNull('g.ends_at')->where('g.ends_at', '>', now('UTC'))->exists();
    }

    private function grants(int $owner): Builder
    {
        return DB::table('generation_grants as g')->join('billing_orders as o', 'o.id', '=', 'g.order_id')
            ->where('g.owner_id', $owner)->where('g.starts_at', '<=', now('UTC'));
    }

    /** @return Collection<int, stdClass> */
    public function sources(int $owner): Collection
    {
        return $this->grants($owner)->where(fn(Builder $query) => $query->whereNull('g.ends_at')->orWhere('g.ends_at', '>', now('UTC'))->orWhere('g.held_seconds', '>', 0))
            ->orderByDesc('g.id')->selectRaw("case when o.status = 'refunded' or ends_at <= ? then 0 else 1 end as active", [now('UTC')])->addSelect(['g.id', 'o.product_code', 'o.amount_kopecks', 'g.starts_at', 'g.ends_at', 'g.granted_seconds', 'g.available_seconds', 'g.held_seconds'])->get();
    }

    public function balance(int $owner): stdClass
    {
        $at = now('UTC');

        /** @var object{available: numeric-string|int, held: numeric-string|int, consumed: numeric-string|int, expired: numeric-string|int} $row */
        $row = $this->grants($owner)
            ->selectRaw('coalesce(sum(case when ends_at <= ? then 0 else available_seconds end), 0) as available', [$at])
            ->selectRaw('coalesce(sum(held_seconds), 0) as held, coalesce(sum(consumed_seconds), 0) as consumed')
            ->selectRaw('coalesce(sum(expired_seconds + case when ends_at <= ? then available_seconds else 0 end), 0) as expired', [$at])
            ->firstOrFail();

        return (object) ['available' => (int) $row->available, 'held' => (int) $row->held, 'consumed' => (int) $row->consumed, 'expired' => (int) $row->expired];
    }

    private function orders(int $owner): Builder
    {
        return DB::table('billing_orders as o')->where('o.owner_id', $owner)
            ->select(['o.id', 'o.product_code', 'o.status', 'o.amount_kopecks', 'o.currency', 'o.seconds', 'o.created_at', 'o.period_starts_at', 'o.period_ends_at'])
            ->addSelect(['payment_status' => DB::table('billing_payments')->select('status')->whereColumn('order_id', 'o.id')->orderByDesc('created_at')->orderByDesc('id')->limit(1)]);
    }

    public function order(int $owner, int $order): stdClass
    {
        return $this->orders($owner)->where('o.id', $order)->firstOrFail();
    }

    public function pendingOrder(int $owner): ?int
    {
        /** @var int|null $id */
        $id = DB::table('billing_orders as o')->join('billing_payments as p', 'p.open_order_id', '=', 'o.id')->where('o.owner_id', $owner)->value('o.id');

        return $id;
    }

    public function confirmationUrl(int $order): ?string
    {
        /** @var string|null */
        return DB::table('billing_payments')->where('open_order_id', $order)->where('status', 'pending')->value('confirmation_url');
    }

    /** @return Collection<int, stdClass> */
    public function refunds(int $order): Collection
    {
        return DB::table('billing_refunds as r')->join('billing_payments as p', 'p.id', '=', 'r.payment_id')->where('p.order_id', $order)
            ->orderByDesc('r.created_at')->get(['r.id', 'r.amount_kopecks', 'r.currency', 'r.status', 'r.created_at']);
    }

    /** @return Collection<int, stdClass> */
    public function receipts(int $order): Collection
    {
        return DB::table('billing_receipts as r')->join('billing_payments as p', 'p.id', '=', 'r.payment_id')->where('p.order_id', $order)
            ->orderByDesc('r.id')->get(['r.id', 'r.kind', 'r.status']);
    }

    /** @return Collection<int, stdClass> */
    public function historyProjects(int $owner): Collection
    {
        return DB::table('projects')->where('projects.owner_id', $owner)
            ->whereExists(fn(Builder $q) => $q->selectRaw('1')->from('generation_reservations as r')
                ->where('r.owner_id', $owner)->where('r.accounting_mode', '!=', GenerationAccountingMode::SHADOW->value)->whereColumn('r.project_id', 'projects.id'))
            ->orderBy('name')->orderBy('id')->get(['id', 'name']);
    }

    /** @return array{data: Collection<int, stdClass>, page: int, last: int} */
    public function history(int $owner, bool $money, int $page, ?int $project): array
    {
        $query = $money ? $this->orders($owner)->orderByDesc('o.id') : DB::table('generation_credit_entries as e')
            ->join('generation_grants as g', 'g.id', '=', 'e.grant_id')->join('billing_orders as o', 'o.id', '=', 'g.order_id')
            ->leftJoin('generation_reservation_parts as part', 'part.id', '=', 'e.part_id')
            ->leftJoin('generation_reservations as r', 'r.id', '=', 'part.reservation_id')
            ->when($project !== null, fn(Builder $q) => $q->where('r.project_id', $project))
            ->where('e.owner_id', $owner)->where(fn(Builder $q) => $q->whereNull('r.id')->orWhere('r.accounting_mode', '!=', GenerationAccountingMode::SHADOW->value))->orderByDesc('e.id')
            ->select(['e.id', 'e.kind', 'e.reason', 'e.created_at', 'e.available_delta', 'e.held_delta', 'e.consumed_delta', 'e.expired_delta', 'e.revoked_delta',
                'o.product_code', 'g.order_id', 'part.shot_id', 'r.project_id', 'r.production_run_id']);
        $results = $query->paginate(15, page: $page);

        /** @var Collection<int, stdClass> $rows */
        $rows = $results->getCollection();

        return ['data' => $rows, 'page' => $results->currentPage(), 'last' => $results->lastPage()];
    }
}
