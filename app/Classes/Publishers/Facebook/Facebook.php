<?php

declare(strict_types=1);

namespace App\Classes\Publishers\Facebook;

use App\Classes\Channels\FacebookOAuth;
use App\Classes\Publishers\AbstractPublisher;
use App\Classes\Publishers\StagedPublisher;
use App\Classes\Publishers\VerifiablePublisher;
use App\Data\PublishVideoData;
use App\Data\VerifiedPublicationData;
use App\Enums\PublicationProviderPhase;
use App\Exceptions\FacebookRateLimitException;
use App\Exceptions\PublicationProcessingException;
use App\Exceptions\PublicationRejectedException;
use App\Exceptions\PublicationVerificationException;
use App\Models\Channel;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use App\Repositories\PublicationRequestRepository;
use App\Support\Integrations\Payload;
use App\Tasks\Channels\IsChannelProjectActive;
use App\Tasks\Posts\SavePost;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class Facebook extends AbstractPublisher implements StagedPublisher, VerifiablePublisher
{
    public function __construct(Channel $channel, Prompt|PublishVideoData $prompt, SavePost $savePost, IsChannelProjectActive $projectActive, private readonly PublicationRequestRepository $requests)
    {
        parent::__construct($channel, $prompt, $savePost, $projectActive);
    }

    public function publish(): bool
    {
        throw new PublicationRejectedException('Facebook доступен только для ручного выпуска эпизода.');
    }

    private function client(): PendingRequest
    {
        $retry = Cache::get('facebook-reels-retry:' . $this->channel->external_account);

        if (is_int($retry) && $retry > now()->timestamp) {
            throw new FacebookRateLimitException(Carbon::createFromTimestampUTC($retry));
        }

        return Http::withToken(Payload::string($this->channel->credentials['access_token'] ?? null, 'access_token'))
            ->withoutRedirecting()->timeout(30)->baseUrl(FacebookOAuth::graph());
    }

    private function sourceVideo(): PublishVideoData
    {
        if (! $this->prompt instanceof PublishVideoData || $this->prompt->episodeAssemblyId === null) {
            throw new PublicationRejectedException('Для Facebook нужна принятая сборка эпизода.');
        }

        return $this->prompt;
    }

    public function createContainer(): string
    {
        $this->assertProjectActive();
        $video = $this->sourceVideo();

        if (! $this->channel->canReceiveManualEpisode()) {
            throw new PublicationRejectedException('Публикация Facebook отключена.');
        }

        $error = FacebookReelFile::error($video->path);

        if ($error !== null) {
            throw new PublicationRejectedException($error);
        }

        $response = $this->client()->post($this->channel->external_account . '/video_reels', ['upload_phase' => 'start']);
        $this->check($response);
        $id = Payload::string($response->json('video_id'), 'video_id');
        $url = Payload::string($response->json('upload_url'), 'upload_url');

        if (! ctype_digit($id) || ! str_starts_with($url, 'https://rupload.facebook.com/') || ! $this->requests->recordFacebookSession($video->publicationRequestId, $id, $url)) {
            throw new RuntimeException('Не удалось сохранить сессию загрузки Facebook.');
        }

        return $id;
    }

    public function publishContainer(string $containerId): void
    {
        $video = $this->sourceVideo();
        $request = $this->requests->findOrFail($video->publicationRequestId);

        if ($request->container_id !== $containerId || ! ctype_digit($containerId)) {
            throw new RuntimeException('Неизвестная сессия Facebook.');
        }

        try {
            $response = $this->client()->get($containerId, ['fields' => 'id,status,permalink_url,from']);

            if ($response->serverError() && $request->created_at?->gt(now()->subDay())) {
                throw new PublicationProcessingException('Facebook временно недоступен. Повторим чтение статуса.');
            }

            try {
                $this->check($response);
            } catch (PublicationRejectedException $exception) {
                if ($request->provider_phase === PublicationProviderPhase::FINISH_REQUESTED) {
                    throw new RuntimeException('Facebook не подтвердил результат отправки.', $exception->getCode(), previous: $exception);
                }

                throw $exception;
            }

            if ($response->json('status.publishing_phase.publish_status') === 'published') {
                $this->assertOwner($response, $containerId);
                $published = $response->json('status.publishing_phase.publish_time');
                $this->savePost($containerId, $this->permalink($response), is_numeric($published) && $published > 0 ? Carbon::createFromTimestampUTC((int) $published) : null);

                return;
            }

            foreach (['processing_phase', 'publishing_phase', 'uploading_phase'] as $phase) {
                if ($response->json('status.' . $phase . '.status') === 'error') {
                    throw new PublicationRejectedException('Facebook отклонил ролик на этапе ' . $phase . '.');
                }
            }

            if ($request->provider_phase === PublicationProviderPhase::FINISH_REQUESTED) {
                if ($request->finish_requested_at?->lt(now()->subDay())) {
                    throw new RuntimeException('Facebook не подтвердил публикацию в течение суток.');
                }

                throw new PublicationProcessingException('Ожидаем подтверждения публикации Facebook.');
            }

            if (in_array($response->json('status.video_status'), ['error', 'expired'], true)) {
                throw new PublicationRejectedException('Facebook отклонил ролик или завершил сессию загрузки.');
            }

            $this->assertProjectActive();

            if (! $this->channel->canReceiveManualEpisode()) {
                throw new PublicationRejectedException('Публикация Facebook отключена.');
            }

            if ($response->json('status.uploading_phase.status') !== 'completed') {
                $this->upload($video->path, $request, $response);

                throw new PublicationProcessingException('Проверяем загрузку ролика Facebook.');
            }

            $client = $this->client();

            if (! $this->requests->claimFacebookFinish($request, $this->channel)) {
                throw new PublicationProcessingException('Ожидаем возможности отправить ролик на страницу.');
            }

            $finish = $client->post($this->channel->external_account . '/video_reels', [
                'upload_phase' => 'finish', 'video_id' => $containerId, 'video_state' => 'PUBLISHED',
                'title' => $video->title, 'description' => $video->description, 'is_ai_generated' => true,
            ]);

            if ($finish->serverError()) {
                throw new PublicationProcessingException('Facebook не подтвердил finish. Проверим статус ролика.');
            }

            try {
                $this->check($finish);
            } catch (FacebookRateLimitException $exception) {
                $this->requests->deferRefusedFacebookFinish($request->id, $exception->retryAt);

                throw $exception;
            }
        } catch (ConnectionException) {
            if ($request->finish_requested_at?->lt(now()->subDay())) {
                throw new RuntimeException('Требуется сверка результата Facebook.');
            }

            throw new PublicationProcessingException('Связь с Facebook прервана. Проверим состояние ролика.');
        }

        throw new PublicationProcessingException('Ожидаем подтверждения публикации Facebook.');
    }

    private function upload(string $path, PublicationRequest $request, Response $response): void
    {
        $offset = $response->json('status.uploading_phase.bytes_transfered');

        $phase = $response->json('status.uploading_phase.status');
        $firstUpload = $request->provider_phase === PublicationProviderPhase::READY_TO_UPLOAD;

        if ($offset === null && ($phase === 'not_started' || ($firstUpload && $phase === 'in_progress'))) {
            $offset = 0;
        }

        if ($offset === null && $phase === 'in_progress' && $request->created_at?->gt(now()->subDay())) {
            throw new PublicationProcessingException('Ожидаем позицию загрузки Facebook.');
        }

        $uploadUrl = $request->upload_url;
        $size = filesize($path);
        $remoteSize = $response->json('status.uploading_phase.source_file_size');

        if (is_numeric($remoteSize) && (int) $remoteSize > 0 && (int) $remoteSize !== $size) {
            throw new RuntimeException('Размер файла Facebook отличается от закреплённого экспорта.');
        }

        if ((! is_int($offset) && (! is_string($offset) || ! ctype_digit($offset))) || $offset < 0 || ! $size || $offset > $size || $uploadUrl === null) {
            throw new RuntimeException('Facebook не подтвердил позицию загрузки.');
        }

        if ($offset < $size) {
            if ($firstUpload && ! $this->requests->beginFacebookUpload($request->id)) {
                throw new PublicationProcessingException('Проверяем состояние загрузки Facebook.');
            }

            $stream = Utils::streamFor(Utils::tryFopen($path, 'rb'));

            try {
                $upload = Http::withoutRedirecting()->timeout(75)->withToken(Payload::string($this->channel->credentials['access_token'] ?? null, 'access_token'), 'OAuth')
                    ->withHeaders(['offset' => (string) $offset, 'file_size' => (string) $size])
                    ->withBody(new LimitStream($stream, -1, (int) $offset), 'application/octet-stream')->post($uploadUrl);

                if ($upload->serverError()) {
                    throw new PublicationProcessingException('Проверим объём принятых Facebook данных.');
                }

                $this->check($upload);
            } finally {
                $stream->close();
            }
        }
    }

    private function check(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $code = $response->json('error.code');
        $request = $this->requests->findOrFail($this->sourceVideo()->publicationRequestId);
        Log::warning('Facebook Reels API error', ['request_id' => $request->id, 'video_id' => $request->container_id,
            'phase' => $request->provider_phase->value ?? 'start', 'code' => is_int($code) ? $code : null, 'status' => $response->status()]);

        if ($response->status() === 429 || in_array($code, [4, 17, 32, 613], true)) {
            $header = $response->header('Retry-After');
            $retryAt = ctype_digit($header) ? now()->addSeconds(max(1, (int) $header))
                : Carbon::createFromTimestampUTC(max(now()->getTimestamp() + 300, \Carbon\Carbon::parse($header)->getTimestamp()));
            Cache::put('facebook-reels-retry:' . $this->channel->external_account, $retryAt->timestamp, $retryAt);

            throw new FacebookRateLimitException($retryAt);
        }

        if (in_array($code, [190, 10, 200], true)) {
            throw new PublicationRejectedException('Facebook отклонил доступ. Переподключите страницу.');
        }

        if ($response->clientError()) {
            throw new PublicationRejectedException('Facebook отклонил запрос. Код ' . (is_int($code) ? $code : $response->status()) . '.');
        }

        throw new RuntimeException('Facebook не подтвердил операцию. HTTP ' . $response->status());
    }

    public function verifyPublication(string $externalId): VerifiedPublicationData
    {
        $request = $this->requests->findOrFail($this->sourceVideo()->publicationRequestId);

        if ($externalId !== $request->container_id || ! ctype_digit($externalId)) {
            throw new PublicationVerificationException('ID не совпадает с закреплённым видео Facebook.');
        }

        $response = $this->client()->get($externalId, ['fields' => 'id,status,from,permalink_url']);
        $this->assertOwner($response, $externalId);

        if ($response->json('status.publishing_phase.publish_status') !== 'published') {
            throw new PublicationVerificationException('Facebook ещё не подтвердил публикацию.');
        }

        return new VerifiedPublicationData($externalId, $this->permalink($response) ?? '');
    }

    private function assertOwner(Response $response, string $id): void
    {
        if ($response->json('id') !== $id || $response->json('from.id') !== $this->channel->external_account) {
            throw new PublicationVerificationException('Facebook не подтвердил принадлежность ролика выбранной странице.');
        }
    }

    private function permalink(Response $response): ?string
    {
        $url = $response->json('permalink_url');

        if (is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return 'https://www.facebook.com' . $url;
        }

        return is_string($url) && str_starts_with($url, 'https://www.facebook.com/') ? $url : null;
    }
}
