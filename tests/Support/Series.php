<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @return array{project: Project, series: Series, character: Character, revision: CharacterRevision, params: array{project: int, series: int, character: int}} */
function serialCharacterContext(): array
{
    $project = Project::factory()->create();
    $series = Series::factory()->for($project)->create();
    $character = Character::factory()->for($series)->create();
    $revision = CharacterRevision::factory()->for($character)->create();

    return [
        'project' => $project,
        'series' => $series,
        'character' => $character,
        'revision' => $revision,
        'params' => ['project' => $project->id, 'series' => $series->id, 'character' => $character->id],
    ];
}

/** @return array{name: string, specification: array{premise: string, visual_style: string, language: string, audience: string, episode_duration_seconds: int, narrative_rules: string, audio_mode: string}} */
function serialSeriesPayload(): array
{
    return [
        'name' => 'Moonlight café',
        'specification' => [
            'premise' => 'Two friends run a café.',
            'visual_style' => 'watercolor animation',
            'language' => 'en',
            'audience' => 'general',
            'episode_duration_seconds' => 30,
            'narrative_rules' => 'End on a question.',
            'audio_mode' => 'native',
        ],
    ];
}

function serialReferenceUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('front.png', File::get(base_path('tests/Fixtures/portrait.png')));
}

function serialReferenceMedia(CharacterRevision $revision): Media
{
    return $revision->addMedia(base_path('tests/Fixtures/portrait.png'))
        ->preservingOriginal()
        ->withCustomProperties(['view' => 'front', 'label' => 'Front view'])
        ->toMediaCollection(CharacterRevision::REFERENCES);
}

/** @return array{episode: Episode, character: CharacterRevision} */
function storyFixture(): array
{
    $series = Series::factory()->create();
    $character = Character::factory()->for($series)->create();
    $revision = CharacterRevision::factory()->for($character)->approved()->create();
    $character->update(['current_revision_id' => $revision->id]);

    return ['episode' => Episode::factory()->for($series)->create(['number' => 1]), 'character' => $revision];
}

/** @return array<string, mixed> */
function storyScriptPayload(CharacterRevision $character): array
{
    return [
        'title' => 'The locked door', 'objective' => 'Discover who left the key.',
        'character_revision_ids' => [$character->id],
        'scenes' => [[
            'title' => 'At the door', 'location' => 'The hallway', 'time' => 'Evening',
            'objective' => 'Find a way inside.', 'initial_state' => 'The door is closed.',
            'expected_final_state' => 'The key is found.', 'is_closed' => false,
            'shots' => [[
                'duration_ms' => 5000, 'action' => 'The hero picks up the key.',
                'camera' => 'Medium shot', 'transition_mode' => 'new_scene', 'dialogue' => [],
            ]],
        ]],
    ];
}
