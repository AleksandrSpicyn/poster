<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Classes\Billing\FiscalProfile;
use App\Data\Billing\MerchantData;
use App\Enums\BillingProduct;
use App\Models\User;
use App\Notifications\BillingRenewalReminder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class BillingRenewalRepository
{
    public function __construct(private BillingWorkspaceRepository $workspace, private BillingPaymentRepository $payments) {}

    public function process(): void
    {
        DB::table('billing_accounts')->whereNotNull('current_product')->where('sales_enabled', true)
            ->where('renews_at', '<=', now('UTC')->addDays(3))
            ->where(fn(Builder $query) => $query->where('renews_at', '>', now('UTC'))->orWhere(fn(Builder $automatic) => $automatic->where('automatic_payments_enabled', true)->where('renewal_consent', true)->whereNotNull('payment_method_id')))
            ->select(['owner_id'])->chunkById(100, function ($rows): void {
                /** @var object{owner_id: int} $row */
                foreach ($rows as $row) {
                    $this->processOwner($row->owner_id);
                }
            }, 'owner_id');
    }

    public function deliverReminders(): void
    {
        $events = DB::table('billing_events')->where('kind', 'renewal.reminder')->where('status', 'pending')->orderBy('id')->limit(100)->get([
            'id',
            'owner_id',
            'payload',
        ]);

        /** @var object{id: int, owner_id: int, payload: string} $event */
        foreach ($events as $event) {
            $row = DB::table('billing_events')->where('id', $event->id);

            if ((clone $row)->where('status', 'pending')->update(['status' => 'sending', 'updated_at' => now('UTC')]) !== 1) {
                continue;
            }

            /** @var array{due: string} $snapshot */
            $snapshot = json_decode($event->payload, true, flags: JSON_THROW_ON_ERROR);
            $user = User::query()->whereNotNull('email_verified_at')->find($event->owner_id);
            /** @var object{renews_at: ?string, next_product: ?string, current_product: ?string, automatic_payments_enabled: bool, renewal_consent: bool, payment_method_id: ?string}|null $account */
            $account = DB::table('billing_accounts')->where('owner_id', $event->owner_id)->where('sales_enabled', true)->first();
            $product = BillingProduct::tryFrom($account->next_product ?? $account->current_product ?? '');

            if ($user === null || $account === null || $product === null || $account->renews_at !== $snapshot['due'] || $snapshot['due'] <= now('UTC')->toDateTimeString()) {
                $row->update(['status' => 'cancelled', 'updated_at' => now('UTC')]);

                continue;
            }

            try {
                $user->notifyNow(new BillingRenewalReminder($snapshot['due'], $product, $account->automatic_payments_enabled && $account->renewal_consent && $account->payment_method_id !== null));
                $row->update(['status' => 'processed', 'updated_at' => now('UTC')]);
            } catch (Throwable $error) {
                report($error);
                $row->update(['status' => 'unknown', 'updated_at' => now('UTC')]);
            }
        }
    }

    private function processOwner(int $owner): void
    {
        DB::transaction(function () use ($owner): void {
            /** @var object{sales_enabled: bool, automatic_payments_enabled: bool, renewal_consent: bool, renews_at: ?string, current_product: ?string, next_product: ?string, consent_revision: int, payment_method_id: ?string, payment_method_test_mode: ?bool, payment_method_provider: ?string, payment_method_shop: ?string} $account */
            $account = DB::table('billing_accounts')->where('owner_id', $owner)->lockForUpdate()->firstOrFail();
            $at = now('UTC');
            $due = $account->renews_at;
            $product = BillingProduct::tryFrom($account->next_product ?? $account->current_product ?? '');

            if (! $account->sales_enabled || $due === null || $product === null || ! $product->isMonthly() || $due > $at->copy()->addDays(3)->toDateTimeString()) {
                return;
            }

            $user = User::query()->whereNotNull('email_verified_at')->find($owner);

            if (! $user instanceof User) {
                return;
            }

            if ($due > $at->toDateTimeString()) {
                $key = 'renewal.reminder.' . $owner . '.' . $due;

                if (! DB::table('billing_events')->where('event_key', $key)->exists()) {
                    DB::table('billing_events')->insert([
                        'owner_id' => $owner,
                        'event_key' => $key,
                        'kind' => 'renewal.reminder',
                        'status' => 'pending',
                        'payload' => json_encode(['product' => $product->value, 'due' => $due], JSON_THROW_ON_ERROR),
                        'created_at' => $at,
                        'updated_at' => $at,
                    ]);
                }

                return;
            }

            if (! $account->automatic_payments_enabled || ! $account->renewal_consent || $account->payment_method_id === null || $account->payment_method_test_mode === null || (! $account->payment_method_test_mode && ! FiscalProfile::liveReady())
                || $account->payment_method_provider === null || $account->payment_method_shop === null || $this->workspace->pendingOrder($owner) !== null) {
                return;
            }

            if (! $this->workspace->hasMandate($owner, $account->consent_revision, $product)) {
                return;
            }

            $key = 'month:' . $due;
            /** @var object{id: int, retry_at: ?string, consent_revision: ?int, product_code: string}|null $existing */
            $existing = DB::table('billing_orders')->where('owner_id', $owner)->where('purchase_key', $key)->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->retry_at === null || $existing->retry_at > $at->toDateTimeString() || $existing->consent_revision !== $account->consent_revision || $existing->product_code !== $product->value) {
                    return;
                }

                $order = $existing->id;
                DB::table('billing_orders')->where('id', $order)->update([
                    'retry_at' => null,
                    'status' => 'open',
                    'updated_at' => $at,
                ]);
            } else {
                $order = DB::table('billing_orders')->insertGetId([
                    'owner_id' => $owner,
                    'purpose' => 'renewal',
                    'purchase_key' => $key,
                    'entitlement_key' => $key,
                    'product_code' => $product->value,
                    'request_key' => (string) Str::uuid(),
                    'catalog_version' => BillingProduct::CATALOG_VERSION,
                    'fiscal_profile' => ($profile = FiscalProfile::configured()) === null ? null : json_encode($profile, JSON_THROW_ON_ERROR),
                    'receipt_email' => $user->email,
                    'consent_revision' => $account->consent_revision,
                    'renewal_due_at' => $due,
                    'amount_kopecks' => $product->amountKopecks(),
                    'seconds' => $product->seconds(),
                    'currency' => 'RUB',
                    'status' => 'open',
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }

            $id = $this->payments->prepare($owner, $order, fn(): MerchantData => new MerchantData($account->payment_method_provider, $account->payment_method_shop, (bool) $account->payment_method_test_mode));
            DB::table('billing_payments')->where('id', $id)->update(['checkout_return_url' => rtrim(config()->string('app.url'), '/') . route('billing.orders.show', $order, false), 'next_check_at' => $at]);
        }, 3);
    }
}
