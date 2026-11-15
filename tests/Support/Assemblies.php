<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Episodes\ApproveEpisodeScriptAction;
use App\Actions\Episodes\SaveEpisodeScriptAction;
use App\Data\Episodes\SaveEpisodeScriptData;
use App\Models\EpisodeRevision;
use App\Models\Prompt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @return array{revision: EpisodeRevision, prompt: Prompt, media: Media} */
function assemblyFixture(): array
{
    ['episode' => $episode, 'character' => $character] = storyFixture();
    $revision = app(SaveEpisodeScriptAction::class)->run($episode, SaveEpisodeScriptData::validateAndCreate([
        'script' => storyScriptPayload($character), 'lock_version' => 0,
    ]));
    $revision = app(ApproveEpisodeScriptAction::class)->run($revision, serialOperator());

    $shot = $revision->scenes()->firstOrFail()->shots()->firstOrFail();
    $series = $episode->series()->firstOrFail();
    $prompt = Prompt::factory()->create([
        'project_id' => $series->project_id, 'shot_id' => $shot->id, 'take_number' => 1, 'generated' => true,
        'input_snapshot' => ['context_hash' => $revision->context_hash,
            'specification_version' => $series->specification_version, 'continuity_version' => $series->continuity_version],
    ]);
    $media = $prompt->addMedia(UploadedFile::fake()->createWithContent('take.mp4', File::get(base_path('tests/Fixtures/shot.mp4'))))
        ->toMediaCollection(Prompt::SHOT_VIDEO);

    return ['revision' => $revision, 'prompt' => $prompt, 'media' => $media];
}
