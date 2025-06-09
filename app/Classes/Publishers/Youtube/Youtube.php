<?php

namespace App\Classes\Publishers\Youtube;

use App\Classes\Publishers\AbstractPublisher;
use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\Exception;
use Google\Service\YouTube as YouTubeService;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoStatus;
use Log;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

class Youtube extends AbstractPublisher
{
    private const CHUNK_SIZE = 1024 * 1024;

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

            if (!$status instanceof Video) {
                throw new RuntimeException('Failed to upload video');
            }

            Log::info('YouTube uploaded video ID: ' . $status->getId(), (array)$status);

            $this->savePost($status->getId(), $this->getUrl($status->getId()));
        } catch (Throwable $e) {
            Log::error('YouTube Publish Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'channel' => $this->channel->id,
                'prompt' => $this->prompt->id,
            ]);

            return false;
        }

        return true;
    }

    private function getClient(): Client
    {
        $client = new Client();

        $client->setApplicationName('Midjourney Shorts Uploader');
        $client->setClientId($this->channel->client_id);
        $client->setClientSecret($this->channel->client_secret);
        $client->setScopes([YouTubeService::YOUTUBE_UPLOAD]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setAccessToken($this->channel->credentials);

        return $client;
    }

    private function updateToken(Client $client): void
    {
        if (!$client->isAccessTokenExpired()) {
            return;
        }

        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        $this->channel->credentials = $client->getAccessToken();
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
        $snippet = new YouTubeService\VideoSnippet();

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
        $youtube = new YouTubeService($client);
        /** @var RequestInterface $insertRequest */
        $insertRequest = $youtube->videos->insert('status,snippet', $video);

        return new MediaFileUpload(
            $client,
            $insertRequest,
            'video/*',
            null,
            true,
            self::CHUNK_SIZE,
        );
    }

    private function upload(MediaFileUpload $media): mixed
    {
        $videoPath = $this->getPath();

        if (!$videoPath) {
            Log::error('YouTube: No media file found.');

            return false;
        }

        $media->setFileSize(filesize($videoPath));

        $handle = fopen($videoPath, 'rb');
        $status = false;

        while (!$status && !feof($handle)) {
            $chunk = fread($handle, self::CHUNK_SIZE);
            $status = $media->nextChunk($chunk);
        }

        fclose($handle);

        return $status;
    }

    public function getUrlTemplate(): string
    {
        return 'https://www.youtube.com/shorts/%s';
    }
}
