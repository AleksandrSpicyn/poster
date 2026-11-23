<?php

declare(strict_types=1);

use App\Actions\Billing\PrepareBillingPaymentAction;
use App\Classes\Billing\PaymentGatewayFactory;
use App\Data\Billing\MerchantData;
use App\Data\Billing\PaymentRequestData;
use App\Data\Billing\RefundRequestData;
use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Fixtures\Billing\AlternatePaymentGateway;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Tests\Support\consoleCommand;

beforeEach(function (): void {
    config(['billing.merchants.sandbox.shop' => '12345', 'billing.merchants.sandbox.credentials.secret' => 'test-secret',
        'billing.drivers.alternate' => ['payments' => AlternatePaymentGateway::class, 'refunds' => AlternatePaymentGateway::class],
        'billing.merchants.other' => ['provider' => 'alternate', 'shop' => '12345', 'test_mode' => true, 'credentials' => []],
    ]);
});

test('фабрика выбирает семейство оплат и возвратов по сохранённому магазину', function (): void {
    $original = app(PaymentGatewayFactory::class)->defaultMerchant();
    config(['billing.default_merchant' => 'other']);
    $factory = app(PaymentGatewayFactory::class);
    $selected = $factory->defaultMerchant();

    expect($selected->provider)->toBe('alternate');
    expect($factory->payments($selected)->findPayment('other-id')->externalId)->toBe('other-id');
    expect($factory->refunds($selected)->findRefund('refund')->paymentId)->toBe('alternate-payment');
    expect($factory->payments($original))->not->toBeInstanceOf(AlternatePaymentGateway::class);
    expect(fn() => $factory->payments(new MerchantData('alternate', '12345', false)))->toThrow(LogicException::class);
    Http::assertNothingSent();
});

test('checkout передаёт точную сумму и повторяет тот же ключ', function (): void {
    $attempt = (string) Str::uuid();
    $key = (string) Str::uuid();
    $data = new PaymentRequestData(29, $attempt, $key, 29_001, 'Разовый пакет', 'https://poster.example/billing/orders/29');
    $factory = app(PaymentGatewayFactory::class);
    $gateway = $factory->payments($factory->defaultMerchant());
    Http::fake(['https://api.yookassa.ru/v3/me' => Http::response(['account_id' => '12345', 'test' => true]), 'https://api.yookassa.ru/v3/payments' => Http::response(paymentReceipt($attempt), 200)]);

    $result = $gateway->createPayment($data);
    $again = $gateway->createPayment($data);

    expect($again)->toEqual($result);
    expect($result->status)->toBe(PaymentStatus::PENDING);
    expect($result->confirmationUrl)->toBe('https://checkout.example/confirm');
    Http::assertSentCount(4);
    Http::assertSent(fn(Request $request): bool => $request->method() === 'POST' && $request->hasHeader('Idempotence-Key', $key)
        && $request['amount'] === ['value' => '290.01', 'currency' => 'RUB']
        && $request['capture'] === true && ! array_key_exists('save_payment_method', $request->data()) && data_get($request->data(), 'metadata.attempt_id') === $attempt);
});

test('чужой магазин, другой режим, валюта и неподтверждённый успех отклоняются', function (array $changes): void {
    $factory = app(PaymentGatewayFactory::class);
    $response = array_replace_recursive(paymentReceipt((string) Str::uuid()), $changes);
    Http::fake(['https://api.yookassa.ru/v3/payments/pay-id' => Http::response($response)]);

    expect(fn() => $factory->payments($factory->defaultMerchant())->findPayment('pay-id'))->toThrow(UnexpectedValueException::class);
})->with([
    'получатель' => [['recipient' => ['account_id' => 'other']]],
    'режим' => [['test' => false]],
    'валюта' => [['amount' => ['currency' => 'USD']]],
    'статус' => [['status' => 'succeeded', 'paid' => false]],
    'платёж' => [['id' => 'another-payment']],
]);

test('ключ боевого магазина в тестовом профиле не отправляет платёж или возврат', function (): void {
    $factory = app(PaymentGatewayFactory::class);
    $merchant = $factory->defaultMerchant();
    $request = new PaymentRequestData(29, (string) Str::uuid(), (string) Str::uuid(), 29000, 'Пакет', 'https://poster.example/orders/29');
    Http::fake(['https://api.yookassa.ru/v3/me' => Http::response(['account_id' => '12345', 'test' => false])]);

    expect(fn() => $factory->payments($merchant)->createPayment($request))->toThrow(UnexpectedValueException::class);
    expect(fn() => $factory->refunds($merchant)->createRefund(new RefundRequestData('pay-id', 100, (string) Str::uuid())))->toThrow(UnexpectedValueException::class);
    Http::assertNotSent(fn(Request $request): bool => $request->method() === 'POST');
});

test('потеря ответа на создание не вызывает второй POST или другой драйвер', function (): void {
    $factory = app(PaymentGatewayFactory::class);
    $data = new PaymentRequestData(29, (string) Str::uuid(), (string) Str::uuid(), 29001, 'Пакет', 'https://poster.example/order/29');
    Http::fake(['https://api.yookassa.ru/v3/me' => Http::response(['account_id' => '12345', 'test' => true]), 'https://api.yookassa.ru/v3/payments' => Http::response([], 503)]);

    expect(fn() => $factory->payments($factory->defaultMerchant())->createPayment($data))->toThrow(RuntimeException::class);

    Http::assertSentCount(2);
});

test('создание и сверка возврата используют исходный платёж и отдельный ключ', function (): void {
    $factory = app(PaymentGatewayFactory::class);
    $gateway = $factory->refunds($factory->defaultMerchant());
    $key = (string) Str::uuid();
    $receipt = ['id' => 'refund-1', 'payment_id' => 'pay-id', 'status' => 'succeeded', 'amount' => ['value' => '1.01', 'currency' => 'RUB']];
    Http::fake(['https://api.yookassa.ru/v3/me' => Http::response(['account_id' => '12345', 'test' => true]), 'https://api.yookassa.ru/v3/refunds*' => Http::response($receipt)]);

    $created = $gateway->createRefund(new RefundRequestData('pay-id', 101, $key));

    expect($gateway->findRefund('refund-1'))->toEqual($created);
    expect($created->status)->toBe(PaymentStatus::SUCCEEDED);
    Http::assertSent(fn(Request $request): bool => $request->method() === 'POST' && $request->hasHeader('Idempotence-Key', $key)
        && $request['payment_id'] === 'pay-id' && data_get($request->data(), 'amount.value') === '1.01');
});

test('смена провайдера по умолчанию не перенаправляет сверку старого платежа', function (): void {
    $owner = User::factory()->create();
    DB::table('billing_accounts')->insert(['owner_id' => $owner->id]);
    $order = DB::table('billing_orders')->insertGetId([
        'owner_id' => $owner->id, 'purchase_key' => 'first', 'request_key' => (string) Str::uuid(), 'entitlement_key' => 'first',
        'purpose' => 'one_off', 'product_code' => 'one_off', 'catalog_version' => 'pilot', 'seconds' => 30,
        'amount_kopecks' => 29001, 'currency' => 'RUB', 'status' => 'open',
    ]);
    $prepare = app(PrepareBillingPaymentAction::class);
    expect(fn() => $prepare->run($owner, $order))->toThrow(DomainException::class);
    $stranger = User::factory()->create();
    expect(fn() => $prepare->run($stranger, $order))->toThrow(AuthorizationException::class);
    DB::table('billing_accounts')->where('owner_id', $owner->id)->update(['sales_enabled' => true]);
    $attempt = $prepare->run($owner, $order);
    DB::table('billing_payments')->where('id', $attempt)->update(['external_id' => 'pay-id', 'status' => 'unknown']);
    config(['billing.default_merchant' => 'other']);
    expect(app(PrepareBillingPaymentAction::class)->run($owner, $order))->toBe($attempt);
    assertDatabaseCount('billing_events', 1);
    assertDatabaseHas('billing_payments', ['id' => $attempt, 'provider' => 'yookassa', 'shop' => '12345', 'test_mode' => true]);
    $body = paymentReceipt($attempt, (string) $order);
    Http::fakeSequence('https://api.yookassa.ru/v3/payments/pay-id')->push($body)->push(array_replace($body, ['amount' => ['currency' => 'RUB', 'value' => '1.00']]));

    consoleCommand('billing:payment-status', ['attempt' => $attempt])->expectsOutput('pending')->assertSuccessful();
    consoleCommand('billing:payment-status', ['attempt' => $attempt])->assertFailed();

    assertDatabaseHas('billing_payments', ['id' => $attempt, 'status' => 'unknown']);
    Http::assertSentCount(2);
    Http::assertNotSent(fn(Request $request): bool => $request->method() !== 'GET');
});

/** @return array<string, mixed> */
function paymentReceipt(string $attempt, string $order = '29'): array
{
    return ['id' => 'pay-id', 'status' => 'pending', 'test' => true, 'recipient' => ['account_id' => '12345'],
        'amount' => ['currency' => 'RUB', 'value' => '290.01'], 'metadata' => ['order_id' => $order, 'attempt_id' => $attempt],
        'confirmation' => ['confirmation_url' => 'https://checkout.example/confirm']];
}
