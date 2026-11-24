<?php

declare(strict_types=1);

namespace App\Classes\Billing\YooKassa;

use App\Classes\Billing\PaymentGateway;
use App\Classes\Billing\RefundGateway;
use App\Data\Billing\MerchantData;
use App\Data\Billing\PaymentRequestData;
use App\Data\Billing\PaymentResultData;
use App\Data\Billing\RefundRequestData;
use App\Data\Billing\RefundResultData;
use App\Enums\PaymentStatus;
use App\Support\Integrations\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use LogicException;
use RuntimeException;
use SensitiveParameter;
use UnexpectedValueException;

final readonly class YooKassaGateway implements PaymentGateway, RefundGateway
{
    private string $secret;

    /** @param array<string, mixed> $credentials */
    public function __construct(private Factory $http, private MerchantData $merchant, #[SensitiveParameter] array $credentials)
    {
        $secret = $credentials['secret'] ?? null;

        if (! is_string($secret) || $secret === '') {
            throw new LogicException('Ключ магазина ЮKassa не настроен.');
        }

        $this->secret = $secret;
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        $response = $this->verifiedMerchant()->withHeader('Idempotence-Key', $request->idempotencyKey)->post('payments', [
            'amount' => $this->amount($request->amountKopecks),
            'capture' => true,
            'description' => $request->description,
            'confirmation' => ['type' => 'redirect', 'return_url' => $request->returnUrl],
            'metadata' => ['order_id' => (string) $request->orderId, 'attempt_id' => $request->attemptId],
            ...($request->savePaymentMethod ? ['save_payment_method' => true] : []),
        ]);
        $result = $this->payment($response);

        if ($result->orderId !== (string) $request->orderId || $result->attemptId !== $request->attemptId || $result->amountKopecks !== $request->amountKopecks) {
            throw new UnexpectedValueException('Подтверждение не соответствует платёжной попытке.');
        }

        return $result;
    }

    public function findPayment(string $externalId): PaymentResultData
    {
        $result = $this->payment($this->client()->get('payments/' . rawurlencode($externalId)));

        if ($result->externalId !== $externalId) {
            throw new UnexpectedValueException('Ответ относится к другому платежу.');
        }

        return $result;
    }

    public function createRefund(RefundRequestData $request): RefundResultData
    {
        $response = $this->verifiedMerchant()->withHeader('Idempotence-Key', $request->idempotencyKey)->post('refunds', [
            'payment_id' => $request->paymentId, 'amount' => $this->amount($request->amountKopecks),
        ]);
        $result = $this->refund($response);

        if ($result->paymentId !== $request->paymentId || $result->amountKopecks !== $request->amountKopecks) {
            throw new UnexpectedValueException('Ответ не соответствует запрошенному возврату.');
        }

        return $result;
    }

    public function findRefund(string $externalId): RefundResultData
    {
        $result = $this->refund($this->client()->get('refunds/' . rawurlencode($externalId)));

        if ($result->externalId !== $externalId) {
            throw new UnexpectedValueException('Ответ относится к другому возврату.');
        }

        return $result;
    }

    private function verifiedMerchant(): PendingRequest
    {
        $client = $this->client();
        $profile = $client->get('me');
        $this->assertSuccess($profile);

        if ($profile->json('account_id') !== $this->merchant->shop || $profile->json('test') !== $this->merchant->testMode) {
            throw new UnexpectedValueException('Реквизиты не соответствуют настроенному магазину или режиму.');
        }

        return $client;
    }

    private function client(): PendingRequest
    {
        return $this->http->baseUrl('https://api.yookassa.ru/v3')->withBasicAuth($this->merchant->shop, $this->secret)
            ->acceptJson()->asJson()->withoutRedirecting()->connectTimeout(5)->timeout(20);
    }

    private function payment(Response $response): PaymentResultData
    {
        $this->assertSuccess($response);

        if ($response->json('recipient.account_id') !== $this->merchant->shop || $response->json('test') !== $this->merchant->testMode) {
            throw new UnexpectedValueException('Ответ относится к другому магазину или режиму.');
        }

        $status = $this->status($response);

        if ($status === PaymentStatus::SUCCEEDED && $response->json('paid') !== true) {
            throw new UnexpectedValueException('Провайдер не подтвердил оплату.');
        }

        $url = $response->json('confirmation.confirmation_url');

        if ($url !== null && (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https')) {
            throw new UnexpectedValueException('Некорректная ссылка на подтверждение оплаты.');
        }

        $savedMethod = $response->json('payment_method.saved') === true ? Payload::string($response->json('payment_method.id'), 'payment_method.id') : null;
        $lastFour = $response->json('payment_method.card.last4');

        $capturedAt = $response->json('captured_at');

        return new PaymentResultData(
            Payload::string($response->json('id'), 'id'),
            $status,
            $this->kopecks($response),
            Payload::string($response->json('metadata.order_id'), 'order_id'),
            Payload::string($response->json('metadata.attempt_id'), 'attempt_id'),
            $url,
            $savedMethod,
            $savedMethod !== null && is_string($lastFour) && preg_match('/\A[0-9]{4}\z/', $lastFour) === 1 ? $lastFour : null,
            is_string($capturedAt) ? CarbonImmutable::parse($capturedAt)->utc() : null,
        );
    }

    private function refund(Response $response): RefundResultData
    {
        $this->assertSuccess($response);

        return new RefundResultData(Payload::string($response->json('id'), 'id'), Payload::string($response->json('payment_id'), 'payment_id'), $this->status($response), $this->kopecks($response));
    }

    private function assertSuccess(Response $response): void
    {
        if (! $response->successful()) {
            throw new RuntimeException('ЮKassa вернула HTTP ' . $response->status() . '. Проверьте исход по сохранённой попытке.');
        }
    }

    private function status(Response $response): PaymentStatus
    {
        return match ($response->json('status')) {
            'pending' => PaymentStatus::PENDING,
            'succeeded' => PaymentStatus::SUCCEEDED,
            'canceled' => PaymentStatus::CANCELLED,
            default => throw new UnexpectedValueException('Неизвестный статус операции ЮKassa.'),
        };
    }

    /** @return array{value: string, currency: string} */
    private function amount(int $kopecks): array
    {
        return ['value' => sprintf('%d.%02d', intdiv($kopecks, 100), $kopecks % 100), 'currency' => 'RUB'];
    }

    private function kopecks(Response $response): int
    {
        $value = $response->json('amount.value');

        if ($response->json('amount.currency') !== 'RUB' || ! is_string($value) || preg_match('/\A(\d{1,12})\.(\d{2})\z/', $value, $parts) !== 1) {
            throw new UnexpectedValueException('Некорректная сумма или валюта операции.');
        }

        return (int) ($parts[1] . $parts[2]);
    }
}
