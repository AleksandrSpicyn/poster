<?php

declare(strict_types=1);

namespace App\Http\Controllers\Episodes;

use App\Actions\Episodes\GetEpisodeAction;
use App\Data\Episodes\EpisodeWorkspaceData;
use App\Enums\TransitionMode;
use App\Http\Resources\CharacterRevisionResource;
use App\Http\Resources\EpisodeAssemblyResource;
use App\Http\Resources\EpisodeResource;
use App\Http\Resources\ProjectChannelResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\PublicationRequestResource;
use App\Http\Resources\SeriesResource;
use App\Models\Episode;
use App\Models\Series;
use Illuminate\Support\Str;
use Inertia\Response;

class ShowController
{
    /**
     * Эпизод, сценарий, дубли и монтаж
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные версии, сцены, планы и сборки ограничены родителями.
     *
     * @group Кабинет
     * @subgroup Эпизоды
     * @authenticated
     * @urlParam series integer required ID сериала. Example: 1
     * @urlParam episode integer required ID эпизода. Example: 1
     * @response 200
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422
     */
    public function __invoke(Series $series, Episode $episode, GetEpisodeAction $action): Response
    {
        $details = fn(): EpisodeWorkspaceData => once(fn(): EpisodeWorkspaceData => $action->run($episode));

        return inertia('Episodes/Show', [
            'series' => fn(): array => SeriesResource::make($details()->episode->series)->resolve(),
            'project' => fn(): array => ProjectResource::make($details()->episode->series?->project)->resolve(),
            'episode' => fn(): array => EpisodeResource::make($details()->episode)->resolve(),
            'characters' => fn(): array => CharacterRevisionResource::collection($details()->characters)->resolve(),
            'assemblies' => fn(): array => EpisodeAssemblyResource::collection($details()->assemblies)->resolve(),
            'publications' => fn(): array => PublicationRequestResource::collection($details()->publications)->resolve(),
            'facebookFileError' => fn(): ?string => $details()->facebookFileError,
            'channels' => fn(): array => ProjectChannelResource::collection($details()->channels)->resolve(),
            'screenplay' => fn(): ?array => $details()->script?->toArray(),
            'requestKey' => (string) Str::uuid(),
            'transitionModes' => TransitionMode::labels(),
            'memoryAssembly' => fn(): ?array => $details()->episode->currentRevision?->sourceAssembly === null
                ? null
                : EpisodeAssemblyResource::make($details()->episode->currentRevision->sourceAssembly)->resolve(),
            'predecessorNumber' => fn(): ?int => $details()->episode->predecessor?->number,
            'sourceAssembly' => fn(): ?array => $details()->episode->predecessor?->acceptedAssembly === null
                ? null
                : EpisodeAssemblyResource::make($details()->episode->predecessor->acceptedAssembly)->resolve(),
        ]);
    }
}
