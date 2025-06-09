<?php

namespace App\Classes\Publishers\Instagram;

use App\Classes\Publishers\AbstractPublisher;
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
                Log::error('Instagram cannot create containe Context', $containerRes->json());

                throw new RuntimeException('Instagram cannot create container for video.');
            }

            sleep(30); // Wait for the container to be ready

            $publishRes = $client->post('media_publish', [
                'creation_id' => $containerId,
            ]);

            $mediaId = $publishRes->json('id');

            if (!$mediaId) {
                Log::error('Instagram Publish Error Context', $publishRes->json());

                throw new RuntimeException('Instagram cannot publish video.');
            }

            $this->savePost($mediaId, $this->getUrl($mediaId));
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
