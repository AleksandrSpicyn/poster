<?php

declare(strict_types=1);

use App\Enums\VideoProvider;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Tests\Support\episodeHttpFixture;

test('оценка кадра использует настройки генерации без запуска и резерва', function (): void {
    config(['services.video.provider' => VideoProvider::SEEDANCE->value, 'services.seedance.model' => 'dreamina-seedance-2-0-mini-260615',
        'services.seedance.resolution' => '720p']);
    $context = episodeHttpFixture();
    $shot = $context['shot'];

    actingAs($context['user'])->getJson(route('billing.usage.shot', $shot))->assertOk()
        ->assertJsonPath('estimate', intdiv($shot->duration_ms, 1000))->assertJsonPath('sources', []);

    assertDatabaseCount('generation_reservations', 0);
    Http::assertNothingSent();
});
