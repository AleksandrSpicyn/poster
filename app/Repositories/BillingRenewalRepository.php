<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Classes\Billing\FiscalProfile;
use App\Data\Billing\MerchantData;
use App\Data\Billing\RenewalReminderData;
use App\Enums\BillingProduct;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /** @return list<int> */
    public function pendingReminderIds(): array
    {
        /** @var list<int> */
        return DB::table('billing_events')->where('kind', 'renewal.reminder')->where('status', 'pending')
            ->orderBy('id')->limit(100)->pluck('id')->all();
    }

    public function claimReminder(int $id): ?RenewalReminderData
    {
        return DB::transaction(function () use ($id): ?RenewalReminderData {
            $event = DB::table('billing_events')->where('id', $id)->where('kind', 'renewal.reminder')->where('status', 'pending');

            /** @var object{owner_id: int, payload: string}|null $row */
            $row = (clone $event)->lockForUpdate()->first(['owner_id', 'payload']);

            if ($row === null) {
                return null;
            }

            /** @var array{due: string} $snapshot */
            $snapshot = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
            $user = User::query()->whereNotNull('email_verified_at')->find($row->owner_id);
            /** @var object{renews_at: ?string, next_product: ?string, current_product: ?string, automatic_payments_enabled: bool, renewal_consent: bool, payment_method_id: ?string, consent_revision: int}|null $account */
            $account = DB::table('billing_accounts')->where('owner_id', $row->owner_id)->where('sales_enabled', true)->first();
            $product = BillingProduct::tryFrom($account->next_product ?? $account->current_product ?? '');

            if ($user === null || $account === null || $product === null || $account->renews_at !== $snapshot['due'] || $snapshot['due'] <= now('UTC')->toDateTimeString()) {
                $event->update(['status' => 'cancelled', 'updated_at' => now('UTC')]);

                return null;
            }

            $automatic = $account->automatic_payments_enabled && $account->renewal_consent && $account->payment_method_id !== null
                && $this->workspace->hasMandate($row->owner_id, $account->consent_revision, $product);
            $event->update(['status' => 'sending', 'updated_at' => now('UTC')]);

            return new RenewalReminderData($user, $snapshot['due'], $product, $automatic);
        });
    }

    public function markReminderUnconfirmed(int $id): void
    {
        DB::table('billing_events')->where('id', $id)->where('status', 'sending')->update(['status' => 'unknown', 'updated_at' => now('UTC')]);
    }

    public function markReminderDelivered(int $id): void
    {
        DB::table('billing_events')->where('id', $id)->where('status', 'sending')->update(['status' => 'processed', 'updated_at' => now('UTC')]);
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
            $this->payments->scheduleCheckout($id, $order, $at);
        }, 3);
    }
}
