<?php

declare(strict_types=1);

use App\Classes\Publishers\PublisherFactory;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Prompt;

use function Pest\Laravel\assertDatabaseCount;

test('незавершённые интеграции не создают публикаций', /** @throws Exception */ function (ChannelType $type): void {
    $channel = Channel::factory()->create(['type' => $type]);
    $prompt = Prompt::factory()->create();

    expect(app(PublisherFactory::class)
        ->make($channel, $prompt)
        ->publish())->toBeFalse();

    assertDatabaseCount('posts', 0);
})->with([ChannelType::TIKTOK, ChannelType::VK]);
