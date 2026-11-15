<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Episodes\ApproveEpisodeScriptAction;
use App\Actions\Episodes\SaveEpisodeScriptAction;
use App\Data\Episodes\SaveEpisodeScriptData;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\Scene;
use App\Models\Shot;
use App\Models\User;

/** @return array{episode: Episode, revision: EpisodeRevision, scene: Scene, shot: Shot, user: User, params: array{series: int, episode: int, revision: int, scene: int, shot: int}} */
function episodeHttpFixture(bool $approved = true): array
{
    $fixture = storyFixture();
    $episode = $fixture['episode'];
    $user = serialOperator();
    $revision = app(SaveEpisodeScriptAction::class)->run($episode, SaveEpisodeScriptData::validateAndCreate([
        'script' => storyScriptPayload($fixture['character']), 'lock_version' => $episode->lock_version,
    ]));

    if ($approved) {
        $revision = app(ApproveEpisodeScriptAction::class)->run($revision, $user);
    }

    $scene = $revision->scenes()->firstOrFail();
    $shot = $scene->shots()->firstOrFail();

    return ['episode' => $episode, 'revision' => $revision, 'scene' => $scene, 'shot' => $shot, 'user' => $user] + ['params' => [
        'series' => $episode->series_id, 'episode' => $episode->id,
        'revision' => $revision->id, 'scene' => $scene->id, 'shot' => $shot->id,
    ]];
}
