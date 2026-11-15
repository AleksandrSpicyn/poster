<?php

declare(strict_types=1);

namespace App\Classes\Publishers\Instagram;

use App\Classes\Publishers\AbstractPublisher;
use App\Enums\ContentType;
use App\Jobs\PublishInstagramJob;
use App\Support\Integrations\Payload;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Публикует Reels в два этапа: создаёт контейнер и откладывает media_publish
 * в PublishInstagramJob; Post сохраняется после получения ID опубликованного media.
 */
class Instagram extends AbstractPublisher
{
    private const string GRAPH_URL = 'https://graph.instagram.com';

    public function publish(): bool
    {
        try {
            $client = $this->getClient();

            $containerRes = $client->post('media', [
                'media_type' => 'REELS',
                'video_url' => $this->prompt->getFirstMediaUrl(),
                'caption' => $this->getTitle(),
                'share_to_feed' => true,
            ]);

            $containerId = $containerRes->json('id');

            if (! $containerId) {
                Log::error('Instagram cannot create container', ['status' => $containerRes->status()]);

                throw new RuntimeException('Instagram cannot create container for video.');
            }

            // Даём Instagram время обработать контейнер; true здесь ещё не означает созданный Post.
            PublishInstagramJob::dispatch($this, Payload::string($containerId, 'id'))
                ->delay(now()->addMinutes(5));
        } catch (Throwable $e) {
            Log::error('Instagram publish failed.', [
                'exception' => $e::class,
                'channel' => $this->channel->id,
                'prompt' => $this->prompt->id,
            ]);

            return false;
        }

        return true;
    }

    protected function getClient(?string $id = null): PendingRequest|Factory
    {
        return Http::withToken(Payload::string($this->channel->credentials['access_token'] ?? null, 'access_token'))
            ->connectTimeout(10)
            ->timeout(60)
            ->baseUrl(
                sprintf(
                    '%s/%s',
                    self::GRAPH_URL,
                    $id ?? Payload::string($this->channel->credentials['channel_id'] ?? null, 'channel_id'),
                ),
            )
            ->withHeaders([
                'Content-Type' => ContentType::JSON->value,
            ]);
    }

    /** @throws ConnectionException */
    public function publishContainer(string $containerId): void
    {
        $client = $this->getClient();

        $publishRes = $client->post('media_publish', [
            'creation_id' => $containerId,
        ]);

        $mediaId = $publishRes->json('id');

        if (! $mediaId) {
            Log::error('Instagram publish failed', ['status' => $publishRes->status()]);

            throw new RuntimeException('Instagram cannot publish video.');
        }

        $mediaId = Payload::string($mediaId, 'id');
        $this->savePost($mediaId, $this->getUrl($mediaId));
    }

    protected function getUrl(string $id): string
    {
        try {
            $url = $this
                ->getClient($id)
                ->get('', [
                    'fields' => 'permalink',
                ])
                ->json('permalink');

            return Payload::string($url, 'permalink');
        } catch (Exception) {
            return '';
        }
    }
}
