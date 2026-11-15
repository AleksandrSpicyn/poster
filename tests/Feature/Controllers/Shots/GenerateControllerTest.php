<?php

declare(strict_types=1);

use App\Enums\VideoProvider;
use App\Jobs\SubmitShotGenerationJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Tests\Support\episodeHttpFixture;
use function Tests\Support\serialReferenceMedia;

test('запуск дубля создаёт долговечный intent до внешней генерации', function (): void {
    config([
        'services.video.provider' => VideoProvider::SEEDANCE->value,
        'services.seedance.model' => 'dreamina-seedance-2-0-mini-260615',
        'services.seedance.resolution' => '720p',
    ]);
    Storage::fake('local');
    $context = episodeHttpFixture();
    $character = $context['revision']->characterRevisions()->firstOrFail();
    serialReferenceMedia($character);
    Bus::fake();
    Process::fake();

    actingAs($context['user'])
        ->post(route('shots.generate', $context['params']), ['request_key' => 'http-shot-request', 'new_take' => false])
        ->assertRedirect(route('episodes.show', ['series' => $context['episode']->series_id, 'episode' => $context['episode']->id]))
        ->assertSessionHasNoErrors();

    $take = $context['shot']->prompts()->firstOrFail();
    expect($take->generationOperations()->first()?->status->value)->toBe('planned');
    expect($take->generated)->toBeFalse();
    Bus::assertDispatched(SubmitShotGenerationJob::class);
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('неутверждённый сценарий не создаёт платный дубль', function (): void {
    Storage::fake('local');
    $context = episodeHttpFixture(false);
    Bus::fake();

    actingAs($context['user'])
        ->postJson(route('shots.generate', $context['params']), [])
        ->assertUnprocessable();

    expect($context['shot']->prompts()->count())->toBe(0);
    Bus::assertNothingDispatched();
});
