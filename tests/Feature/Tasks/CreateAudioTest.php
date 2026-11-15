<?php

declare(strict_types=1);

use App\Settings\GeneralSettings;
use App\Tasks\CreateAudio;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('запрашивает короткую инструментальную музыку с развитием и развязкой', function (): void {
    GeneralSettings::fake(['duration' => 8]);
    config(['services.suno_unofficial.api_url' => 'https://suno.example.test', 'services.suno_unofficial.api_key' => 'test']);
    Http::fake(['https://suno.example.test/generate' => Http::response(['data' => ['taskId' => 'music-1']])]);

    $id = app(CreateAudio::class)->run('A fox races a runaway basket');

    expect($id)->toBe('music-1');
    Http::assertSent(function (Request $request): bool {
        expect($request['instrumental'])->toBeTrue();
        expect($request['style'])->toContain('rhythmic groove');
        expect($request['prompt'])->toContain('A fox races a runaway basket', 'instrumental background music', '8 seconds', 'rising rhythmic tension', 'final reversal', 'No vocals');

        return true;
    });
    Http::assertSentCount(1);
});
