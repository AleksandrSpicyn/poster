<?php

declare(strict_types=1);

namespace App\Classes\Publishers\Tiktok;

use App\Classes\Publishers\AbstractPublisher;
use App\Classes\Publishers\StagedPublisher;
use App\Classes\Publishers\VerifiablePublisher;
use App\Classes\Tiktok\TiktokClient;
use App\Data\PublishVideoData;
use App\Data\VerifiedPublicationData;
use App\Exceptions\PublicationProcessingException;
use App\Exceptions\PublicationRejectedException;
use App\Exceptions\PublicationVerificationException;
use App\Models\Channel;
use App\Models\Prompt;
use App\Support\Integrations\Payload;
use App\Tasks\Channels\IsChannelProjectActive;
use App\Tasks\Posts\SavePost;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

class Tiktok extends AbstractPublisher implements StagedPublisher, VerifiablePublisher
{
    public function __construct(Channel $channel, Prompt|PublishVideoData $prompt, SavePost $savePost, IsChannelProjectActive $projectActive, private readonly TiktokClient $client)
    {
        parent::__construct($channel, $prompt, $savePost, $projectActive);
    }

    /** Legacy prompts have no persistent upload receipt. Inbox delivery uses a publication request. */
    public function publish(): bool
    {
        return false;
    }

    public function createContainer(): string
    {
        $this->assertProjectActive();

        if (! $this->prompt instanceof PublishVideoData || $this->channel->external_account !== null) {
            throw new RuntimeException('Choose a series publishing account for TikTok inbox delivery.');
        }

        if (! $this->channel->canSendTiktokInboxVideo()) {
            throw new PublicationRejectedException('Reconnect the series channel with TikTok inbox upload permission.');
        }

        $http = $this->client->forChannel($this->channel);
        $response = $http->post('post/publish/inbox/video/init/', [
            'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $this->getVideoUrl()],
        ]);

        return Payload::string($this->client->data($response, 'publish_id'), 'publish_id');
    }

    public function publishContainer(string $containerId): void
    {
        try {
            $response = $this->client->forChannel($this->channel)->post('post/publish/status/fetch/', ['publish_id' => $containerId]);
        } catch (ConnectionException) {
            throw new PublicationProcessingException('Waiting for TikTok status.');
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new PublicationProcessingException('Waiting for TikTok status.');
        }

        $status = $this->client->data($response, 'status');

        if ($status === 'FAILED') {
            throw new PublicationRejectedException('TikTok rejected the video.');
        }

        if ($status === 'SEND_TO_USER_INBOX') {
            throw new PublicationProcessingException('Open the notification in your TikTok inbox to publish the video.');
        }

        if (in_array($status, ['PROCESSING_UPLOAD', 'PROCESSING_DOWNLOAD'], true)) {
            throw new PublicationProcessingException('TikTok is processing the video.');
        }

        if ($status !== 'PUBLISH_COMPLETE') {
            throw new RuntimeException('Unknown TikTok publication status.');
        }

        $id = $response->json('data.publicaly_available_post_id.0');
        $id = is_int($id) || is_string($id) ? (string) $id : null;
        $this->savePost($id, $id === null ? null : 'https://www.tiktok.com/player/v1/' . rawurlencode($id));
    }

    public function verifyPublication(string $externalId): VerifiedPublicationData
    {
        if (! preg_match('/^[0-9]{1,20}$/D', $externalId)) {
            throw new PublicationVerificationException('Enter the numeric TikTok video ID.');
        }

        $video = $this->client->videos($this->channel, [$externalId])[0] ?? [];

        if (($video['id'] ?? null) !== $externalId) {
            throw new PublicationVerificationException('TikTok did not confirm ownership of this public video.');
        }

        return new VerifiedPublicationData($externalId, 'https://www.tiktok.com/player/v1/' . $externalId);
    }
}
