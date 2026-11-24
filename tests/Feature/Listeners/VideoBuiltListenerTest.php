<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Events\VideoBuilt;
use App\Jobs\AdvanceVideoUpscaleJob;
use App\Jobs\PublishEpisodeJob;
use App\Models\Channel;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use App\Tasks\Publications\ClaimPublication;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Tests\Support\workspaceOwner;

test('готовый ролик запускает публикацию во всех каналах проекта', function (): void {
    Storage::fake('local');
    Bus::fake();

    $project = Project::factory()->recycle(workspaceOwner())->create();

    Channel::factory()->recycle(workspaceOwner())
        ->for($project)
        ->count(2)
        ->create([
            'type' => ChannelType::INSTAGRAM,
            'credentials' => ['access_token' => 'test-token', 'channel_id' => 'ig-1'],
        ]);

    $prompt = Prompt::factory()->recycle(workspaceOwner())
        ->for($project)
        ->create(['generated' => true]);
    $prompt->addMedia(base_path('tests/Fixtures/shot.mp4'))->preservingOriginal()->toMediaCollection();

    Http::fake(['*/ig-1/media' => Http::response(['id' => 'container-1'])]);

    event(new VideoBuilt($prompt));

    expect($prompt->upscales()->count())->toBe(1);
    expect(PublicationRequest::query()->count())->toBe(2);
    expect(app(ClaimPublication::class)->run(PublicationRequest::query()->firstOrFail()))->toBeFalse();
    Http::assertNothingSent();
    Bus::assertDispatchedTimes(PublishEpisodeJob::class, 2);
    Bus::assertDispatchedTimes(AdvanceVideoUpscaleJob::class, 1);
});
