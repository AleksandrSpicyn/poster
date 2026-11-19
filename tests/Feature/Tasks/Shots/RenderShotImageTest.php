<?php

declare(strict_types=1);

use App\Enums\TransitionMode;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Tasks\Shots\RenderShotImage;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

test('продолжает сцену последним видеокадром когда звук длиннее изображения', function (): void {
    $disk = Storage::fake('local');
    $path = $disk->path('source.mp4');
    Process::run(['ffmpeg', '-nostdin', '-v', 'error', '-f', 'lavfi', '-i', 'color=red:s=64x96:r=24:d=0.5',
        '-f', 'lavfi', '-i', 'anullsrc=r=48000:cl=mono:d=2', '-c:v', 'libx264', '-c:a', 'aac', $path])->throw();
    $source = Prompt::factory()->create()->addMedia($path)->toMediaCollection(Prompt::SHOT_VIDEO, 'local');
    $prompt = Prompt::factory()->create();
    $operation = new GenerationOperation(['prompt_id' => $prompt->id, 'request_snapshot' => [
        'transition_mode' => TransitionMode::CONTINUE_SCENE->value, 'source_media_id' => $source->id,
        'source_sha256' => hash_file('sha256', $source->getPath()), 'source_time_ms' => 0,
    ]]);

    $image = app(RenderShotImage::class)->run($operation);

    expect(getimagesize($image->getPath()))->toMatchArray([0 => 64, 1 => 96]);
    expect($image->collection_name)->toBe(Prompt::SHOT_IMAGE);
    expect($disk->allFiles('shot-work'))->toBe([]);
});
