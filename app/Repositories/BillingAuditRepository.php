<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class BillingAuditRepository
{
    /** @return array<string, int> */
    public function counts(): array
    {
        $entries = DB::table('generation_credit_entries')->groupBy('grant_id')->select('grant_id');
        $counters = [
            'granted',
            'available',
            'held',
            'consumed',
            'expired',
            'revoked',
        ];

        foreach ($counters as $counter) {
            $entries->selectRaw("sum({$counter}_delta) as {$counter}");
        }

        return [
            'Расхождения счётчиков и журнала' => DB::table('generation_grants as g')->leftJoinSub($entries, 'e', 'e.grant_id', '=', 'g.id')
                ->where(function (Builder $query) use ($counters): void {
                    foreach ($counters as $counter) {
                        $query->orWhereRaw("g.{$counter}_seconds <> coalesce(e.{$counter}, 0)");
                    }
                })->count(),
            'Оплаченные заказы без пакета' => DB::table('billing_orders as o')->leftJoin('generation_grants as g', 'g.order_id', '=', 'o.id')
                ->whereIn('o.status', ['paid', 'partially_refunded'])->whereNull('g.id')->count(),
            'Неизвестные платежи' => DB::table('billing_payments')->where('status', 'unknown')->count(),
            'Неизвестные возвраты' => DB::table('billing_refunds')->where('status', 'unknown')->count(),
            'Письма с неподтверждённой отправкой' => DB::table('billing_events')->where('kind', 'renewal.reminder')
                ->where(fn(Builder $query) => $query->where('status', 'unknown')->orWhere(fn(Builder $stale) => $stale->where('status', 'sending')->where('updated_at', '<', now('UTC')->subHour())))->count(),
            'Чеки на разборе' => DB::table('billing_receipts')->whereIn('status', ['unknown', 'cancelled'])->count(),
            'Резервы старше суток' => DB::table('generation_reservations')->where('accounting_mode', 'enforced')->where('status', 'held')->where('updated_at', '<', now('UTC')->subDay())->count(),
            'Платежи для ручной сверки' => DB::table('billing_events')->where('status', 'needs_review')->count(),
        ];
    }
}
