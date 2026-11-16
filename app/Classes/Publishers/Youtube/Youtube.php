<?php

declare(strict_types=1);

namespace App\Classes\Publishers\Youtube;

use App\Classes\Publishers\AbstractPublisher;
use App\Classes\Publishers\VerifiablePublisher;
use App\Data\PublishVideoData;
use App\Data\VerifiedPublicationData;
use App\Exceptions\PublicationVerificationException;
use App\Models\Channel;
use App\Models\Prompt;
use App\Support\Integrations\Payload;
use App\Tasks\Channels\IsChannelProjectActive;
use App\Tasks\Channels\SaveChannelCredentials;
use App\Tasks\Posts\SavePost;
use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\Exception;
use Google\Service\YouTube as YouTubeService;
use Google\Service\YouTube\Channel as YoutubeChannel;
use Google\Service\YouTube\Resource\Channels;
use Google\Service\YouTube\Resource\Videos;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

/**
 * Загружает готовый локальный ролик порциями через YouTube API, обновляя OAuth
 * при необходимости; после ответа Video сохраняет Post со ссылкой на Shorts.
 */
class Youtube extends AbstractPublisher implements VerifiablePublisher
{
    private const int CHUNK_SIZE = 1024 * 1024;

    public function __construct(Channel $channel, Prompt|PublishVideoData $prompt, private readonly Client $client, SavePost $savePost, IsChannelProjectActive $projectActive, private readonly SaveChannelCredentials $saveCredentials)
    {
        parent::__construct($channel, $prompt, $savePost, $projectActive);
    }

    public function publish(): bool
    {
        try {
            $this->assertProjectActive();

            $client = $this->getClient();
            $client->setDefer(true);
            $this->updateToken($client);

            $video = new Video();
            $this->setStatus($video);
            $this->setSnippet($video);

            $media = $this->makeMedia($client, $video);
            $status = $this->upload($media);

            if (! $status instanceof Video) {
                throw new RuntimeException('Failed to upload video');
            }

            Log::info('YouTube uploaded video ID: ' . $status->getId(), (array) $status);

            $id = Payload::string($status->getId(), 'id');
            $this->savePost($id, $this->getUrl($id));
        } catch (Throwable $e) {
            Log::error('YouTube publish failed.', [
                'exception' => $e::class,
                'channel' => $this->channel->id,
                'source' => $this->getSourceId(),
            ]);

            return false;
        }

        return true;
    }

    public function verifyPublication(string $externalId): VerifiedPublicationData
    {
        if (preg_match('/\A[A-Za-z0-9_-]{1,128}\z/', $externalId) !== 1) {
            throw new PublicationVerificationException('Enter the YouTube video ID rather than its URL.');
        }

        $client = $this->getClient(readOnly: true);
        $client->setDefer(false);
        $this->updateToken($client);
        /** @var YouTubeService&object{channels: Channels, videos: Videos} $youtube */
        $youtube = new YouTubeService($client);
        $ownedChannels = $youtube->channels->listChannels('id', ['mine' => true, 'maxResults' => 50])->getItems();
        $videos = $youtube->videos->listVideos('snippet', ['id' => $externalId])->getItems();
        $video = $videos[0] ?? null;

        if (! $video instanceof Video || $video->getId() !== $externalId) {
            throw new PublicationVerificationException('YouTube did not return the selected video.');
        }

        $channelId = Payload::string($video->getSnippet()->getChannelId(), 'video.channelId');
        $owned = collect($ownedChannels)->contains(static fn(YoutubeChannel $channel): bool => $channel->getId() === $channelId);

        if (! $owned) {
            throw new PublicationVerificationException('This YouTube video does not belong to the authorised channel.');
        }

        return new VerifiedPublicationData($externalId, $this->getUrl($externalId));
    }

    private function getClient(bool $readOnly = false): Client
    {
        $client = $this->client;

        $client->setApplicationName('Midjourney Shorts Uploader');
        $client->setClientId(Payload::string($this->channel->client_id, 'client_id'));
        $client->setClientSecret(Payload::string($this->channel->client_secret, 'client_secret'));
        $client->setScopes([$readOnly ? YouTubeService::YOUTUBE_READONLY : YouTubeService::YOUTUBE_UPLOAD]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setAccessToken($this->channel->credentials ?? []);

        return $client;
    }

    private function updateToken(Client $client): void
    {
        if (! $client->isAccessTokenExpired()) {
            return;
        }

        $token = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

        if (isset($token['error'])) {
            throw new RuntimeException('YouTube token refresh failed.');
        }

        $this->saveCredentials->run($this->channel, $token, preserveMetadata: true);
    }

    private function setStatus(Video $video): void
    {
        $status = new VideoStatus();

        $status->privacyStatus = 'public';

        if ($this->prompt instanceof PublishVideoData) {
            $status->selfDeclaredMadeForKids = $this->prompt->madeForKids;
        }

        $video->setStatus($status);
    }

    private function setSnippet(Video $video): void
    {
        $snippet = new VideoSnippet();

        $snippet->setTitle($this->getTitle());
        $snippet->setDescription($this->getDescription());
        $snippet->setCategoryId('22');

        $video->setSnippet($snippet);
    }

    /**
     * @throws Exception
     */
    private function makeMedia(Client $client, Video $video): MediaFileUpload
    {
        /** @var YouTubeService&object{videos: Videos} $youtube */
        $youtube = new YouTubeService($client);
        /** @var RequestInterface $insertRequest Defer mode returns the upload request. */
        $insertRequest = $youtube->videos->insert('status,snippet', $video);

        return new MediaFileUpload(
            $client,
            $insertRequest,
            'video/*',
            '',
            true,
            self::CHUNK_SIZE,
        );
    }

    private function upload(MediaFileUpload $media): mixed
    {
        $videoPath = $this->getPath();

        if (! $videoPath) {
            Log::error('YouTube: No media file found.');

            return false;
        }

        $size = filesize($videoPath);
        $handle = fopen($videoPath, 'rb');

        if ($size === false || $handle === false) {
            throw new RuntimeException('Cannot read video for YouTube upload.');
        }

        $media->setFileSize($size);
        $status = false;

        try {
            while (! $status && ! feof($handle)) {
                $chunk = fread($handle, self::CHUNK_SIZE);

                if ($chunk === false) {
                    throw new RuntimeException('Cannot read video chunk.');
                }

                $status = $media->nextChunk($chunk);
            }
        } finally {
            fclose($handle);
        }

        return $status;
    }

    public function getUrlTemplate(): string
    {
        return 'https://www.youtube.com/shorts/%s';
    }
}
