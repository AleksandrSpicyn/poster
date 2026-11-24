<?php

declare(strict_types=1);

use App\Models\OpusClip;
use App\Models\OpusSource;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

use function Tests\Support\consoleCommand;

test('сравнивает просмотры одной публикации на две даты внутри проекта', function (): void {
    $project = Project::factory()->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    $clip = OpusClip::factory()->for(OpusSource::factory()->for($project)->state(['game' => 'Demo']), 'source')->create(['title' => 'Boss fight']);

    foreach ([7 => 0, 0 => 25] as $days => $views) {
        $clip->latestMetrics()->create(['platform' => 'youtube', 'media_id' => 'fixture-media', 'observed_on' => now('UTC')->subDays($days)->toDateString(), 'metrics' => ['views' => $views]]);
    }

    $clip->latestMetrics()->create(['platform' => 'instagram', 'media_id' => 'fixture-reel', 'observed_on' => now('UTC')->toDateString(), 'metrics' => ['views' => 80]]);

    DB::enableQueryLog();

    try {
        consoleCommand('opus:performance')
            ->expectsTable(['Game', 'Clip', 'View change', 'Total views'], [['Demo', 'Boss fight', 25, 25]])
            ->expectsOutput('No publications with view counts on both dates.')
            ->assertSuccessful();
        $reads = collect(DB::getQueryLog())->pluck('query')->filter(
            fn(mixed $sql): bool => is_string($sql) && preg_match('/from [`"]opus_(clip_metrics|clips|sources)[`"]/i', $sql) === 1,
        );
    } finally {
        DB::disableQueryLog();
    }

    expect($reads)->toHaveCount(1);
});

test('выводит десять наибольших изменений включая отрицательные', function (): void {
    $project = Project::factory()->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    $source = OpusSource::factory()->for($project)->create(['game' => 'Demo']);
    $expected = [];

    foreach (range(0, 11) as $index) {
        $clip = OpusClip::factory()->for($source, 'source')->create(['title' => "Clip $index"]);

        foreach ([7 => 12, 0 => $index] as $days => $views) {
            $clip->latestMetrics()->create([
                'platform' => 'tiktok',
                'media_id' => (string) $index,
                'observed_on' => now('UTC')->subDays($days)->toDateString(),
                'metrics' => ['views' => $views],
            ]);
        }

        if ($index >= 2) {
            $expected[] = ['Demo', "Clip $index", $index - 12, $index];
        }
    }

    consoleCommand('opus:performance')
        ->expectsTable(['Game', 'Clip', 'View change', 'Total views'], array_reverse($expected))
        ->assertSuccessful();
});
