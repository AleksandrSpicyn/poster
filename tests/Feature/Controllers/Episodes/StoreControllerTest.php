<?php

declare(strict_types=1);

use App\Models\Episode;
use App\Models\Series;
use Illuminate\Support\Facades\Process;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Tests\Support\serialOperator;

test('создание эпизода не запускает генерацию', function (): void {
    $series = Series::factory()->create();
    Process::fake();

    $response = actingAs(serialOperator())->post(route('episodes.store', $series), ['title' => 'The first meeting']);

    $episode = Episode::query()->firstOrFail();
    $response->assertRedirect(route('episodes.show', ['series' => $series, 'episode' => $episode]));
    expect($episode->number)->toBe(1);
    expect($episode->series_id)->toBe($series->id);
    Process::assertNothingRan();
});

test('гость не создаёт эпизод', function (): void {
    $series = Series::factory()->create();

    post(route('episodes.store', $series), ['title' => 'First'])->assertRedirect(route('login'));

    expect($series->episodes()->count())->toBe(0);
});
