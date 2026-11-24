<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Billing\MerchantData;
use App\Data\Billing\PaymentResultData;
use App\Enums\PaymentStatus;
use App\Events\WorkspaceUpdated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class BillingSettlementRepository
{
    public function ownerForOrder(int $order): int
    {
        /** @var int */
        return DB::table('billing_orders')->where('id', $order)->value('owner_id');
    }

    /** @return object{calendar_anchor_at: ?string, consent_revision: int, renewal_consent: bool} */
    public function lockAccount(int $owner): object
    {
        /** @var object{calendar_anchor_at: ?string, consent_revision: int, renewal_consent: bool} */
        return DB::table('billing_accounts')->where('owner_id', $owner)->lockForUpdate()->firstOrFail();
    }

    /** @return object{id: int, product_code: string, seconds: int, entitlement_key: string, purpose: string, consent_revision: ?int, renewal_due_at: ?string, status: string, period_starts_at: ?string, period_ends_at: ?string} */
    public function lockOrder(int $order): object
    {
        /** @var object{id: int, product_code: string, seconds: int, entitlement_key: string, purpose: string, consent_revision: ?int, renewal_due_at: ?string, status: string, period_starts_at: ?string, period_ends_at: ?string} */
        return DB::table('billing_orders')->where('id', $order)->lockForUpdate()->firstOrFail();
    }

    public function hasEntitlement(int $owner, string $key): bool
    {
        return DB::table('generation_grants')->where('owner_id', $owner)->where('entitlement_key', $key)->exists();
    }

    public function hasActiveRefund(string $payment): bool
    {
        return DB::table('billing_refunds')->where('payment_id', $payment)->where('status', '!=', 'cancelled')->exists();
    }

    public function attemptCount(int $order): int
    {
        return DB::table('billing_payments')->where('order_id', $order)->count();
    }

    public function recordAttempt(string $id, PaymentResultData $result, PaymentStatus $status, ?string $confirmationUrl, bool $finished, CarbonImmutable $at): void
    {
        DB::table('billing_payments')->where('id', $id)->update([
            'reason' => $result->cancellationReason,
            'status' => $status->value,
            'external_id' => $result->externalId,
            'confirmation_url' => $finished ? null : ($result->confirmationUrl ?? $confirmationUrl),
            'confirmed_at' => $finished ? $at : null,
            'next_check_at' => $finished ? null : $at->addMinute(),
            'updated_at' => $at,
        ]);
        DB::table('billing_events')->where('event_key', 'payment.prepared.' . $id)
            ->update([
                'status' => 'processed',
                'next_check_at' => null,
                'updated_at' => $at,
            ]);
    }

    public function finishOrder(int $id, bool $paid, bool $retry, CarbonImmutable $starts, ?CarbonImmutable $ends, CarbonImmutable $at): void
    {
        DB::table('billing_orders')->where('id', $id)->update([
            'retry_at' => $retry ? $at->addDays(2) : null,
            'status' => $paid ? 'paid' : 'cancelled',
            'period_starts_at' => $paid ? $starts : null,
            'period_ends_at' => $ends,
            'updated_at' => $at,
        ]);
    }

    public function disableRenewalAfterCancellation(int $owner, CarbonImmutable $at): void
    {
        DB::table('billing_accounts')->where('owner_id', $owner)->update(['renewal_consent' => false, 'updated_at' => $at]);
    }

    public function savePaymentMethod(int $owner, MerchantData $merchant, PaymentResultData $result): void
    {
        DB::table('billing_accounts')->where('owner_id', $owner)->update([
            'renewal_consent' => $result->savedMethodId !== null,
            'payment_method_id' => $result->savedMethodId,
            'payment_method_last_four' => $result->lastFour,
            'payment_method_provider' => $merchant->provider,
            'payment_method_shop' => $merchant->shop,
            'payment_method_test_mode' => $merchant->testMode,
        ]);
    }

    public function disableRenewal(int $owner): void
    {
        DB::table('billing_accounts')->where('owner_id', $owner)->update(['renewal_consent' => false]);
    }

    public function startSubscription(int $owner, string $product, CarbonImmutable $anchor, ?CarbonImmutable $ends, CarbonImmutable $at): void
    {
        DB::table('billing_accounts')->where('owner_id', $owner)->update([
            'current_product' => $product,
            'next_product' => null,
            'calendar_anchor_at' => $anchor,
            'renews_at' => $ends,
            'updated_at' => $at,
        ]);
    }

    public function flagExcess(int $owner, int $order, string $attempt, CarbonImmutable $at): void
    {
        DB::table('billing_events')->insert([
            'owner_id' => $owner,
            'event_key' => 'payment.excess.' . $attempt,
            'kind' => 'payment.excess',
            'status' => 'needs_review',
            'payload' => json_encode(['order' => $order, 'attempt' => $attempt], JSON_THROW_ON_ERROR),
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function grant(int $owner, int $order, string $key, int $seconds, CarbonImmutable $starts, ?CarbonImmutable $ends): void
    {
        $at = CarbonImmutable::now('UTC');
        $grant = DB::table('generation_grants')->insertGetId([
            'order_id' => $order,
            'owner_id' => $owner,
            'entitlement_key' => $key,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'granted_seconds' => $seconds,
            'available_seconds' => $seconds,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        DB::table('generation_credit_entries')->insert([
            'owner_id' => $owner,
            'grant_id' => $grant,
            'kind' => 'grant',
            'event_key' => 'checkout.granted.' . $order,
            'granted_delta' => $seconds,
            'available_delta' => $seconds,
            'created_at' => $at,
        ]);
        WorkspaceUpdated::forBalance($owner);
    }
}
