<?php

declare(strict_types=1);

namespace App\Classes\Publishers\Tiktok;

use App\Classes\Publishers\AbstractPublisher;
use App\Classes\Publishers\StagedPublisher;
use App\Classes\Publishers\VerifiablePublisher;
use App\Classes\Tiktok\TiktokClient;
use App\Data\PublishVideoData;
use App\Data\Tiktok\TiktokPublicationData;
use App\Data\VerifiedPublicationData;
use App\Enums\TiktokDeliveryMode;
use App\Exceptions\PublicationProcessingException;
use App\Exceptions\PublicationRejectedException;
use App\Exceptions\PublicationVerificationException;
use App\Jobs\PublishEpisodeJob;
use App\Models\Channel;
use App\Models\Prompt;
use App\Repositories\PublicationRequestRepository;
use App\Support\Integrations\Payload;
use App\Tasks\Channels\IsChannelProjectActive;
use App\Tasks\Posts\SavePost;
use App\Tasks\Tiktok\CreatorSettings;
use App\Tasks\Tiktok\ValidateDirectPost;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class Tiktok extends AbstractPublisher implements StagedPublisher, VerifiablePublisher
{
    public function __construct(Channel $channel, Prompt|PublishVideoData $prompt, SavePost $savePost, IsChannelProjectActive $projectActive, private readonly TiktokClient $client, private readonly CreatorSettings $settings, private readonly ValidateDirectPost $validate, private readonly PublicationRequestRepository $requests)
    {
        parent::__construct($channel, $prompt, $savePost, $projectActive);
    }

    /** Legacy prompts have no persistent upload receipt. Inbox delivery uses a publication request. */
    public function publish(): bool
    {
        $ownerId = $this->channel->owner_id;

        if (! $this->prompt instanceof Prompt || ! $this->channel->canSendTiktokInboxVideo() || $ownerId === null) {
            return false;
        }

        $this->assertProjectActive();
        $request = $this->requests->forShort($this->prompt, $this->channel, $ownerId);

        if ($request->wasRecentlyCreated) {
            PublishEpisodeJob::dispatch($request->id)->afterCommit();
        }

        return true;
    }

    public function createContainer(): string
    {
        $this->assertProjectActive();

        if (! $this->prompt instanceof PublishVideoData || $this->channel->external_account !== null) {
            throw new RuntimeException('Choose a series publishing account for TikTok inbox delivery.');
        }

        $post = $this->prompt->tiktok;

        if ($post instanceof TiktokPublicationData && ($post->account_id !== ($this->channel->credentials['channel_id'] ?? null) || $post->media_id < 1)) {
            throw new PublicationRejectedException('The selected TikTok account changed.');
        }

        if ($post instanceof TiktokPublicationData && $post->mode === TiktokDeliveryMode::DIRECT) {
            if (! $this->channel->canDirectPostTiktok()) {
                throw new PublicationRejectedException('Direct Post permission is unavailable.');
            }

            try {
                $this->validate->run($post, $this->settings->run($this->channel), $this->prompt->path);
            } catch (ValidationException|RuntimeException|ConnectionException $exception) {
                throw new PublicationRejectedException('TikTok creator settings could not be verified. Review the publication settings.', $exception->getCode(), previous: $exception);
            }

            $reply = $this->client->forChannel($this->channel, 'video.publish')->post('post/publish/video/init/', [
                'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $this->getVideoUrl()],
                'post_info' => ['title' => $post->title ?? '', 'privacy_level' => $post->privacy, 'disable_comment' => ! $post->comment,
                    'disable_duet' => ! $post->duet, 'disable_stitch' => ! $post->stitch, 'brand_content_toggle' => $post->partner,
                    'brand_organic_toggle' => $post->own_brand, 'is_aigc' => $post->ai],
            ]);

            return Payload::string($this->client->data($reply, 'publish_id'), 'publish_id');
        }

        if (! $this->channel->canSendTiktokInboxVideo()) {
            throw new PublicationRejectedException('Reconnect the series channel with TikTok inbox upload permission.');
        }

        $http = $this->client->forChannel($this->channel, 'video.upload');
        $response = $http->post('post/publish/inbox/video/init/', [
            'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $this->getVideoUrl()],
        ]);

        return Payload::string($this->client->data($response, 'publish_id'), 'publish_id');
    }

    public function publishContainer(string $containerId): void
    {
        try {
            $options = $this->prompt instanceof PublishVideoData ? $this->prompt->tiktok : null;

            if ($options instanceof TiktokPublicationData && $options->account_id !== ($this->channel->credentials['channel_id'] ?? null)) {
                throw new RuntimeException('Restore the original TikTok account to verify this receipt.');
            }

            $scope = $options?->mode === TiktokDeliveryMode::DIRECT ? 'video.publish' : 'video.upload';
            $response = $this->client->forChannel($this->channel, $scope)->post('post/publish/status/fetch/', ['publish_id' => $containerId]);
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
            throw new PublicationProcessingException('Open the notification in your TikTok inbox to publish the video.', inbox: true);
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
