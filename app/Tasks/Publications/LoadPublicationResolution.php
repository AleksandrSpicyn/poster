<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Classes\Publications\PublicationReference;
use App\Data\Publications\PreparedPublicationData;
use App\Data\Publications\PublicationResolutionData;
use App\Data\PublishVideoData;
use App\Enums\ChannelType;
use App\Enums\PublicationStatus;
use App\Models\PublicationRequest;
use App\Models\User;
use App\Repositories\ChannelRepository;
use App\Repositories\PostRepository;
use App\Repositories\PublicationRequestRepository;
use App\Repositories\UserRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LoadPublicationResolution
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ChannelRepository $channels,
        private readonly PublicationRequestRepository $publications,
        private readonly PostRepository $posts,
    ) {}

    public function run(PublicationRequest $request, User $actor, PublicationReference $reference, bool $lock = false): ?PreparedPublicationData
    {
        $context = $this->context($request, $actor, $lock);
        $request = $context->request;
        $channel = $context->channel;

        if ($reference->platform instanceof ChannelType && $reference->platform !== $channel->type) {
            throw ValidationException::withMessages(['external_id' => 'Ссылка должна вести на площадку выбранного канала.']);
        }

        if ($request->status === PublicationStatus::PUBLISHED
            && $this->posts->hasReceipt($request, $reference)) {
            return null;
        }

        if ($request->status !== PublicationStatus::SUBMISSION_UNKNOWN) {
            throw ValidationException::withMessages(['publication' => 'Ручная сверка доступна только для отправок с неизвестным результатом.']);
        }

        if (! $channel->canVerifyPublication()) {
            throw ValidationException::withMessages(['publication' => 'Для сверки подключите канал с правом чтения публикаций.']);
        }

        if ($this->posts->receiptUsedElsewhere($channel, $request, $reference->value)) {
            throw ValidationException::withMessages(['external_id' => 'Это видео уже связано с другой публикацией.']);
        }

        return new PreparedPublicationData($channel, new PublishVideoData(
            '',
            '',
            $request->payload['title'],
            $request->payload['description'],
            $request->episode_assembly_id,
            $request->id,
            $request->payload['made_for_kids'],
            $request->series_assembly_id,
            short: $context->short,
        ), $request);
    }

    public function context(PublicationRequest $request, User $actor, bool $lock = false): PublicationResolutionData
    {
        $actor = $this->users->fresh($actor);

        if (! $actor instanceof User) {
            throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('manageSeries', $request);

        $context = $this->publications->resolutionContext($request, $lock);
        $request = $context->request;
        $channel = $context->channel;

        if ($request->created_by !== $actor->id || $channel->owner_id !== $actor->id || $context->project->owner_id !== $actor->id || ! $this->channels->belongsToProject($channel, $context->project->id)) {
            throw ValidationException::withMessages(['publication' => 'Проверьте привязку канала к проекту и доступ к аккаунту.']);
        }

        return $context;
    }
}
