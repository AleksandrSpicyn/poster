<?php

declare(strict_types=1);

namespace App\Classes\Publishers\Youtube;

use App\Classes\Publishers\AbstractPublisher;
use App\Models\Channel;
use App\Models\Prompt;
use App\Support\Integrations\Payload;
use App\Tasks\Posts\SavePost;
use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\Exception;
use Google\Service\YouTube as YouTubeService;
use Google\Service\YouTube\Resource\Videos;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

class Youtube extends AbstractPublisher
{
    private const int CHUNK_SIZE = 1024 * 1024;

    public function __construct(Channel $channel, Prompt $prompt, private readonly Client $client, SavePost $savePost)
    {
        parent::__construct($channel, $prompt, $savePost);
    }

    public function publish(): bool
    {
        try {
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
                'prompt' => $this->prompt->id,
            ]);

            return false;
        }

        return true;
    }

    private function getClient(): Client
    {
        $client = $this->client;

        $client->setApplicationName('Midjourney Shorts Uploader');
        $client->setClientId(Payload::string($this->channel->client_id, 'client_id'));
        $client->setClientSecret(Payload::string($this->channel->client_secret, 'client_secret'));
        $client->setScopes([YouTubeService::YOUTUBE_UPLOAD]);
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

        $this->channel->credentials = $token;
        $this->channel->save();
    }

    private function setStatus(Video $video): void
    {
        $status = new VideoStatus();

        $status->privacyStatus = 'public';

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
