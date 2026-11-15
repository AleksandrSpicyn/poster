<?php

declare(strict_types=1);

use App\Enums\VideoProvider;
use App\Events\AudioGenerated;
use App\Events\VideoGenerated;
use App\Events\VideoGenerating;
use App\Jobs\CheckVideoCreation;
use App\Jobs\SaveVideoJob;
use App\Models\Prompt;
use Illuminate\Support\Facades\Bus;

test('события генерации направляются в соответствующие очереди', function (): void {
    Bus::fake();

    $prompt = Prompt::factory()->create();

    event(new VideoGenerating($prompt, true));
    event(new AudioGenerated($prompt));
    event(new VideoGenerated($prompt));

    Bus::assertDispatched(CheckVideoCreation::class);
    Bus::assertDispatchedTimes(SaveVideoJob::class, 2);
});

test('лимит опроса берётся из контракта выбранного драйвера', function (VideoProvider $provider, int $attempts): void {
    Bus::fake();
    $prompt = Prompt::factory()->create(['video_provider' => $provider]);

    event(new VideoGenerating($prompt));

    Bus::assertDispatched(CheckVideoCreation::class, fn(CheckVideoCreation $job): bool => $job->tries === $attempts);
})->with([
    'Hailuo' => [VideoProvider::HAILUO, 10],
    'Runway' => [VideoProvider::RUNWAY, 10],
    'Seedance' => [VideoProvider::SEEDANCE, 61],
]);
