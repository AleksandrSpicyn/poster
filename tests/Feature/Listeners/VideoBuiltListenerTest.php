<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Events\VideoBuilt;
use App\Jobs\PublishInstagramJob;
use App\Models\Channel;
use App\Models\Project;
use App\Models\Prompt;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

test('готовый ролик запускает публикацию во всех каналах проекта', function (): void {
    Bus::fake();

    $project = Project::factory()->create();

    Channel::factory()
        ->for($project)
        ->count(2)
        ->create([
            'type' => ChannelType::INSTAGRAM,
            'credentials' => ['access_token' => 'test-token', 'channel_id' => 'ig-1'],
        ]);

    $prompt = Prompt::factory()
        ->for($project)
        ->create();

    Http::fake(['*/ig-1/media' => Http::response(['id' => 'container-1'])]);

    event(new VideoBuilt($prompt));

    Http::assertSentCount(2);
    Bus::assertDispatchedTimes(PublishInstagramJob::class, 2);
});
