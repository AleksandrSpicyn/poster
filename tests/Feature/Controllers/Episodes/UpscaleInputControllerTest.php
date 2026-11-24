<?php

declare(strict_types=1);

use App\Enums\UpscaleOperationStatus;
use App\Enums\UpscaleProvider;
use App\Models\EpisodeAssembly;
use App\Models\VideoUpscaleOperation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\get;
use function Tests\Support\acceptedEpisodePublication;

test('ссылка fal выдаёт только закреплённый вход действующей операции', function (): void {
    Storage::fake('local');
    $fixture = acceptedEpisodePublication();
    $assembly = $fixture['assembly'];
    $media = $assembly->addMedia(UploadedFile::fake()->createWithContent('input.mp4', 'private-input'))->toMediaCollection(EpisodeAssembly::UPSCALE_INPUT);
    $checksum = hash('sha256', 'private-input');
    $operation = VideoUpscaleOperation::query()->create([
        'episode_assembly_id' => $assembly->id, 'initiator_id' => $fixture['user']->id, 'attempt' => 1, 'operation_key' => str_repeat('a', 64),
        'provider' => UpscaleProvider::FAL, 'status' => UpscaleOperationStatus::SUBMITTING, 'source_snapshot' => [], 'profile_snapshot' => [],
        'currency' => 'USD', 'estimated_cost_e8' => 0,
    ]);
    $operation->source_media_id = $media->id;
    $operation->source_sha256 = $checksum;
    $operation->save();

    $params = ['operation' => $operation->id, 'checksum' => $checksum];
    $url = URL::temporarySignedRoute('upscales.input', now()->addHour(), $params);

    get(route('upscales.input', $params))->assertForbidden();
    get($url)->assertOk();

    $operation->update(['status' => UpscaleOperationStatus::READY]);
    get($url)->assertNotFound();
});
