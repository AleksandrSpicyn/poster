<?php

declare(strict_types=1);

use App\Classes\Media\Upscale\VideoUpscalerFactory;
use App\Enums\UpscaleExport;
use App\Enums\UpscaleOperationStatus;
use App\Enums\UpscaleProvider;
use App\Models\EpisodeAssembly;
use App\Models\User;
use App\Models\VideoUpscaleOperation;
use App\Repositories\VideoUpscaleOperationRepository;
use Illuminate\Validation\ValidationException;

use function Tests\Support\episodeHttpFixture;

test('повтор использует ту же попытку до подтверждённого отказа', function (): void {
    $fixture = episodeHttpFixture();
    $manifest = ['shots' => [[
        'media_id' => 1, 'prompt_id' => 2, 'checksum' => str_repeat('a', 64),
        'trim_start_ms' => 0, 'trim_end_ms' => 5000, 'duration_ms' => 5000,
    ]]];
    $assembly = EpisodeAssembly::factory()->for($fixture['revision'])->ready()->create([
        'manifest' => $manifest, 'manifest_hash' => hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR)),
    ]);
    $repository = app(VideoUpscaleOperationRepository::class);
    $capabilities = app(VideoUpscalerFactory::class)->make(UpscaleProvider::FAL)->capabilities();
    $plan = fn(User $owner, ?int $retry = null): VideoUpscaleOperation => $repository->plan(
        $assembly,
        $owner,
        UpscaleProvider::FAL,
        $capabilities,
        UpscaleExport::HD,
        4_000_000,
        $manifest,
        $retry,
    );
    $owner = $fixture['user'];

    expect(fn(): VideoUpscaleOperation => $plan(User::factory()->create()))->toThrow(ValidationException::class);
    $operation = $plan($owner);
    expect($plan($owner)->id)->toBe($operation->id)
        ->and($operation->source_snapshot)->toEqual($manifest)
        ->and($operation->profile_snapshot['export'])->toBe('720p');
    $operation->update(['status' => UpscaleOperationStatus::SUBMISSION_UNKNOWN]);
    expect($plan($owner, $operation->id)->id)->toBe($operation->id);
    $operation->update(['status' => UpscaleOperationStatus::FAILED]);
    expect($plan($owner)->id)->toBe($operation->id);

    $second = $plan($owner, $operation->id);

    expect($second->attempt)->toBe(2)
        ->and($plan($owner, $operation->id)->id)->toBe($second->id)
        ->and(VideoUpscaleOperation::query()->count())->toBe(2);
});
