<?php

namespace App\Classes\Publishers\Instagram;

use App\Classes\Publishers\AbstractPublisher;
use App\Jobs\PublishInstagramJob;
use Exception;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class Instagram extends AbstractPublisher
{
    private const GRAPH_URL = 'https://graph.instagram.com';

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

            if (!$containerId) {
                Log::error('Instagram cannot create container context', $containerRes->json());

                throw new RuntimeException('Instagram cannot create container for video.');
            }

            PublishInstagramJob::dispatch($this, $containerId)
                ->delay(now()->addMinutes(5));
        } catch (Throwable $e) {
            Log::error('Instagram Publish Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'channel' => $this->channel->id,
                'prompt' => $this->prompt->id,
            ]);

            return false;
        }

        return true;
    }

    protected function getClient(?string $id = null): PendingRequest|Factory
    {
        return Http::withToken($this->channel->credentials['access_token'])
            ->baseUrl(
                sprintf(
                    '%s/%s',
                    self::GRAPH_URL,
                    $id ?? $this->channel->credentials['channel_id'],
                ),
            )
            ->withHeaders([
                'Content-Type' => 'application/json',
            ]);
    }

    public function publishContainer(string $containerId): void
    {
        $client = $this->getClient();

        $publishRes = $client->post('media_publish', [
            'creation_id' => $containerId,
        ]);

        $mediaId = $publishRes->json('id');

        if (!$mediaId) {
            Log::error('Instagram Publish Error Context', $publishRes->json());

            throw new RuntimeException('Instagram cannot publish video.');
        }

        $this->savePost($mediaId, $this->getUrl($mediaId));
    }

    public function getUrl(string $id): string
    {
        try {
            return $this
                ->getClient($id)
                ->post('', [
                    'fields' => 'permalink',
                    'comment_enabled' => true,
                ])
                ->json('permalink');
        } catch (Exception) {
            return '';
        }
    }
}
