<?php

declare(strict_types=1);

use App\Data\Series\SeriesSpecificationData;
use App\Settings\GeneralSettings;
use App\Tasks\CreateVideo;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('новая генерация использует пятнадцать секунд по умолчанию', function (): void {
    config(['services.video.provider' => 'seedance', 'services.seedance.api_key' => 'test', 'services.seedance.model' => 'dreamina-seedance-2-0-mini-260615']);
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'default-duration'])]);

    $video = app(CreateVideo::class)->run('https://example.test/first.png', 'A cat opens a box.');

    expect(app(GeneralSettings::class)->duration)->toBe(15);
    expect($video->request?->duration)->toBe(15);
    expect(SeriesSpecificationData::from(['premise' => 'A story'])->episode_duration_seconds)->toBe(15);
    Http::assertSent(fn(Request $request): bool => $request['duration'] === 15);
});
