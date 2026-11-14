<?php

declare(strict_types=1);

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
