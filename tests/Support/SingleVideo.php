<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Data\Series\SeriesAssemblyManifestData;
use App\Enums\AssemblyStatus;
use App\Enums\ChannelType;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\ProjectContentMode;
use App\Enums\SeriesOutputMode;
use App\Models\Channel;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use App\Models\SeriesAssembly;
use App\Models\User;

/** @return array{project: Project, series: Series, run: ProductionRun, channel: Channel, user: User} */
function singleVideoFixture(int $count = 3): array
{
    $user = serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create(['content_mode' => ProjectContentMode::SERIES]);
    $parts = array_map(fn(int $number): array => ['number' => $number, 'objective' => 'Move forward ' . $number,
        'expected_result' => 'Progress ' . $number, 'scene_key' => 'room', 'transition_mode' => $number === 1 ? 'new_scene' : 'continue_scene'], range(1, $count));
    $series = Series::factory()->recycle(workspaceOwner())->for($project)->create(['output_mode' => SeriesOutputMode::SINGLE_VIDEO,
        'automation_enabled' => true, 'automation_user_id' => $user->id, 'episode_limit' => $count,
        'cycle_plan' => ['objective' => 'One complete story', 'structure' => 'continuous_story', 'parts' => $parts]]);
    $previous = null;

    for ($number = 1; $number <= $count; $number++) {
        $episode = Episode::factory()->recycle(workspaceOwner())->for($series)->create(['number' => $number, 'predecessor_episode_id' => $previous?->id]);
        $revision = EpisodeRevision::factory()->for($episode)->create();
        $assembly = EpisodeAssembly::factory()->for($revision)->create(['status' => AssemblyStatus::ACCEPTED, 'duration_ms' => 1000,
            'continuity_snapshot' => ['confirmed_events' => 'Progress ' . $number, 'scene_closed' => true]]);
        $assembly->addMedia(base_path('tests/Fixtures/shot.mp4'))->preservingOriginal()
            ->withCustomProperties(['checksum' => hash_file('sha256', base_path('tests/Fixtures/shot.mp4'))])->toMediaCollection(EpisodeAssembly::VIDEO);
        $episode->update(['accepted_assembly_id' => $assembly->id, 'current_revision_id' => $revision->id]);
        $previous = $episode;
    }

    $channel = Channel::factory()->recycle(workspaceOwner())->for($project)->create(['type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'test']]);
    $run = ProductionRun::factory()->recycle(workspaceOwner())->for($project)->for($series)->create(['actor_id' => $user->id, 'episode_id' => $previous?->id,
        'status' => ProductionStatus::WAITING, 'stage' => ProductionStage::FINAL_MONTAGE,
        'input_snapshot' => ['output_mode' => 'single_video', 'channel_ids' => [$channel->id], 'episode_limit' => $count,
            'specification_version' => $series->specification_version, 'specification' => $series->specification]]);

    return ['project' => $project, 'series' => $series, 'run' => $run, 'channel' => $channel, 'user' => $user];
}

function markSeriesBoundariesReviewed(SeriesAssembly $assembly): void
{
    $parts = SeriesAssemblyManifestData::fromSnapshot($assembly->manifest)->parts;
    $boundaries = collect(array_slice($parts, 0, -1))->mapWithKeys(fn(array $part): array => [
        'after_' . $part['number'] => ['reviewed' => true, 'notes' => 'Переход проверен'],
    ]);
    $assembly->update(['review' => ['boundaries' => $boundaries->all()]]);
}
