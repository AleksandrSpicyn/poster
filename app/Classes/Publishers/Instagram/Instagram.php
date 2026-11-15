<?php

declare(strict_types=1);

namespace App\Classes\Publishers\Instagram;

use App\Classes\Publishers\AbstractPublisher;
use App\Classes\Publishers\StagedPublisher;
use App\Classes\Publishers\VerifiablePublisher;
use App\Data\VerifiedPublicationData;
use App\Enums\ContentType;
use App\Exceptions\PublicationVerificationException;
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
class Instagram extends AbstractPublisher implements StagedPublisher, VerifiablePublisher
{
    private const string GRAPH_URL = 'https://graph.instagram.com';

    public function publish(): bool
    {
        try {
            $containerId = $this->createContainer();

            // Даём Instagram время обработать контейнер; true здесь ещё не означает созданный Post.
            PublishInstagramJob::dispatch($this, $containerId)
                ->delay(now()->addMinutes(5));
        } catch (Throwable $e) {
            Log::error('Instagram publish failed.', [
                'exception' => $e::class,
                'channel' => $this->channel->id,
                'source' => $this->getSourceId(),
            ]);

            return false;
        }

        return true;
    }

    public function createContainer(): string
    {
        $this->assertProjectActive();

        $response = $this->getClient()->post('media', [
            'media_type' => 'REELS',
            'video_url' => $this->getVideoUrl(),
            'caption' => $this->getTitle(),
            'share_to_feed' => true,
        ])->throw();

        return Payload::string($response->json('id'), 'id');
    }

    public function verifyPublication(string $externalId): VerifiedPublicationData
    {
        if (preg_match('/\A[0-9]{1,64}\z/', $externalId) !== 1) {
            throw new PublicationVerificationException('Enter the numeric Instagram media ID.');
        }

        $response = $this->getClient($externalId)->get('', ['fields' => 'id,permalink,owner{id},media_type'])->throw();
        $id = $response->json('id');
        $owner = $response->json('owner.id');
        $url = $response->json('permalink');
        $expectedOwner = $this->channel->credentials['channel_id'] ?? null;

        if (! is_string($id) || $id !== $externalId || ! is_string($owner) || $owner !== $expectedOwner
            || $response->json('media_type') !== 'VIDEO') {
            throw new PublicationVerificationException('Instagram did not confirm that this video belongs to the configured account.');
        }

        if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || ! in_array(parse_url($url, PHP_URL_HOST), ['instagram.com', 'www.instagram.com'], true)) {
            throw new PublicationVerificationException('Instagram did not return a valid video permalink.');
        }

        return new VerifiedPublicationData($id, $url);
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
        $this->assertProjectActive();

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
