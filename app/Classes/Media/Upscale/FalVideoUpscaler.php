<?php

declare(strict_types=1);

namespace App\Classes\Media\Upscale;

use App\Data\Upscale\UpscaleCapabilitiesData;
use App\Data\Upscale\UpscaleReceiptData;
use App\Data\Upscale\UpscaleRequestData;
use App\Data\Upscale\UpscaleStatusData;
use App\Enums\UpscaleQueueStatus;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final readonly class FalVideoUpscaler extends AbstractAsyncVideoUpscaler
{
    private const string ENDPOINT = 'https://queue.fal.run/topaz/upscale/video/precision';

    public function __construct(#[Config('services.fal.key')] private string $key) {}

    public function capabilities(): UpscaleCapabilitiesData
    {
        return new UpscaleCapabilitiesData('Proteus', 300_000, 486, 864, 24);
    }

    protected function send(UpscaleRequestData $request): UpscaleReceiptData
    {
        $profile = $this->capabilities();
        $response = $this->client()->post(self::ENDPOINT, [
            'video_url' => $request->sourceUrl,
            'model' => $profile->model,
            'upscale_factor' => $request->export->width() / $profile->sourceWidth,
        ]);

        if (in_array($response->status(), [400, 401, 402, 403, 404, 422, 429], true)) {
            throw new UpscaleRejected('fal rejected upscale submission (HTTP ' . $response->status() . ').');
        }

        $id = $response->json('request_id');
        $status = $response->json('status_url');
        $result = $response->json('response_url');

        if (! $response->successful() || ! is_string($id) || trim($id) === ''
            || ! is_string($status) || ! $this->isQueueUrl($status)
            || ! is_string($result) || ! $this->isQueueUrl($result)) {
            throw new SubmissionUnknown('fal did not return a valid submission receipt.');
        }

        return new UpscaleReceiptData($id, $status, $result);
    }

    protected function poll(UpscaleReceiptData $receipt): UpscaleStatusData
    {
        $response = $this->read($receipt->statusUrl);

        return match ($response->json('status')) {
            'IN_QUEUE' => new UpscaleStatusData(UpscaleQueueStatus::QUEUED),
            'IN_PROGRESS' => new UpscaleStatusData(UpscaleQueueStatus::PROCESSING),
            'COMPLETED' => $this->result($receipt),
            default => throw new PollUnavailable('Unrecognized fal queue status.'),
        };
    }

    private function result(UpscaleReceiptData $receipt): UpscaleStatusData
    {
        $response = $this->read($receipt->resultUrl);
        $url = $response->json('video.url');

        if ($response->json('error') !== null || ! is_string($url)
            || preg_match('~\\Ahttps://(?:[a-z0-9-]+\\.)*fal\\.media/~i', $url) !== 1) {
            throw new UpscaleRejected('fal returned no usable upscale result.');
        }

        return new UpscaleStatusData(UpscaleQueueStatus::READY, $url);
    }

    private function read(string $url): Response
    {
        if (! $this->isQueueUrl($url)) {
            throw new InvalidArgumentException('Invalid fal queue URL.');
        }

        $response = $this->client()->get($url);

        if ($response->status() === 422) {
            throw new UpscaleRejected('fal could not process this video (HTTP 422).');
        }

        if (! $response->successful()) {
            throw new PollUnavailable('fal queue read failed (HTTP ' . $response->status() . ').');
        }

        return $response;
    }

    private function isQueueUrl(string $url): bool
    {
        return str_starts_with($url, 'https://queue.fal.run/');
    }

    private function client(): PendingRequest
    {
        if (trim($this->key) === '') {
            throw new InvalidArgumentException('Missing fal API key.');
        }

        return Http::withToken($this->key, 'Key')
            ->withoutRedirecting()
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30);
    }
}
