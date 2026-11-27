<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;

test('пробу можно прослушать до сохранения и затем закрепить как образец', function (): void {
    Storage::fake('local');
    config(['services.byteplus_speech.api_key' => 'test-speech-key']);
    $voice = 'multi_male_mpho_v3_uranus_bigtts';
    $url = "https://voice.ap-southeast-1.bytepluses.com/api/v3/text-to-speech/$voice/streaming";
    $context = serialCharacterContext();
    $params = [...$context['params'], 'revision' => $context['revision']];
    $part = str_repeat(pack('v', 120), 24000);
    $stream = collect([
        ['event' => 'ready'],
        ['event' => 'audio', 'audio' => base64_encode($part)],
        ['event' => 'audio', 'audio' => base64_encode($part)],
        ['event' => 'final', 'is_final' => true, 'reason' => 'TTS_STREAM_FINAL_REASON_CLOSED'],
    ])->map(fn(array $event): string => json_encode($event, JSON_THROW_ON_ERROR))->implode("\n");
    Http::fake([$url => Http::response($stream)]);

    $response = actingAs(serialOperator())
        ->postJson(route('characters.revisions.voice_preview', $params), ['voice_id' => $voice, 'text' => 'Я тебя слышу.', 'lock_version' => 0]);

    $response->assertOk()->assertHeader('Content-Type', 'audio/wav');
    $wav = $response->getContent();
    expect($wav)->toBeString()->toStartWith('RIFF');
    expect(substr((string) $wav, 44))->toBe($part . $part);
    expect($context['revision']->media()->exists())->toBeFalse();
    Http::assertSent(fn(Request $request): bool => $request->url() === $url && $request->hasHeader('X-Api-Key', 'test-speech-key')
        && $request['text'] === 'Я тебя слышу.' && $request['model_id'] === 'dola-seed-tts-3.0' && $request['output_format'] === ['format' => 'pcm', 'sample_rate' => 24000]);

    post(route('characters.references.store', $params), ['file' => UploadedFile::fake()->createWithContent('voice.wav', (string) $wav), 'view' => 'voice', 'lock_version' => 0])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($context['revision']->media()->firstOrFail()->getCustomProperty('duration_ms'))->toBe(2000);
    Http::assertSentCount(1);
});

test('недоступная проба не отправляет запрос провайдеру', function (string $key, string $voice, int $version, int $status): void {
    config(['services.byteplus_speech.api_key' => $key]);
    Http::fake();
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->postJson(route('characters.revisions.voice_preview', [...$context['params'], 'revision' => $context['revision']]), [
            'voice_id' => $voice, 'text' => 'Откройте ворота.', 'lock_version' => $version,
        ])
        ->assertStatus($status);

    Http::assertNothingSent();
})->with([
    'нет ключа' => ['', 'multi_male_mpho_v3_uranus_bigtts', 0, 503],
    'неизвестный голос' => ['key', 'other', 0, 422],
    'черновик изменился' => ['key', 'multi_male_mpho_v3_uranus_bigtts', 3, 422],
]);

test('ошибка доступа и оборванный ответ не возвращают аудио', function (int $upstream, int $expected): void {
    config(['services.byteplus_speech.api_key' => 'test-key']);
    Http::fake(['*' => Http::response("{\"event\":\"ready\"}\n{\"event\":\"audio\",\"audio\":\"YQ==\"}", $upstream)]);
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->postJson(route('characters.revisions.voice_preview', [...$context['params'], 'revision' => $context['revision']]), [
            'voice_id' => 'multi_male_mpho_v3_uranus_bigtts', 'text' => 'Я здесь.', 'lock_version' => 0,
        ])
        ->assertStatus($expected);

    expect($context['revision']->media()->exists())->toBeFalse();
})->with([[403, 503], [200, 502]]);

test('после обновления карточки проигрывается второй из двух дублей', function (): void {
    Storage::fake('local');
    config(['services.byteplus_speech.api_key' => 'test-speech-key']);
    $context = serialCharacterContext();
    $params = [...$context['params'], 'revision' => $context['revision']];
    $previewUrl = route('characters.revisions.voice_preview', $params);
    $sentence = ['text' => 'Я тебя слышу.', 'lock_version' => 0];
    $voices = ['multi_male_mpho_v3_uranus_bigtts', 'multi_female_sophie_v3_uranus_bigtts'];
    Http::fake(function (Request $request) use ($voices) {
        $value = str_contains($request->url(), $voices[0]) ? 100 : 200;
        $chunk = base64_encode(str_repeat(pack('v', $value), 48000));

        return Http::response(implode('
', [
            '{"event":"ready"}',
            json_encode(['event' => 'audio', 'audio' => $chunk], JSON_THROW_ON_ERROR),
            '{"event":"final","is_final":true,"reason":"TTS_STREAM_FINAL_REASON_CLOSED"}',
        ]));
    });

    $checksums = [];

    foreach ($voices as $voice) {
        $response = actingAs(serialOperator())->postJson($previewUrl, [...$sentence, 'voice_id' => $voice])
            ->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $wav = (string) $response->getContent();
        $checksums[] = hash('sha256', $wav);
    }

    expect($checksums[0])->not->toBe($checksums[1]);
    expect($context['revision']->media()->exists())->toBeFalse();

    post(route('characters.references.store', $params), [
        'file' => UploadedFile::fake()->createWithContent('chosen.wav', $wav), 'view' => 'voice', 'lock_version' => 0,
    ])->assertRedirectToRoute('characters.show', $context['params'])->assertSessionHasNoErrors();
    $media = $context['revision']->media()->sole();
    $url = route('characters.references.show', ['revision' => $context['revision'], 'media' => $media]);
    get(route('characters.show', $context['params']))->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
        ->where('revisions.data.0.voice_sample.url', $url)
        ->where('revisions.data.0.voice_sample.checksum', $checksums[1]));
    $audio = get($url)->assertOk()->baseResponse;
    assert($audio instanceof BinaryFileResponse);
    expect(hash_file('sha256', $audio->getFile()->getPathname()))->toBe($checksums[1]);
    Http::assertSentCount(2);
});

test('подмена родителя в адресе пробы скрывает ревизию', function (string $part): void {
    config(['services.byteplus_speech.api_key' => 'test']);
    Http::fake();
    $context = serialCharacterContext();
    $other = serialCharacterContext();
    $params = [...$context['params'], 'revision' => $context['revision'], $part => $other[$part]];

    actingAs(serialOperator())->postJson(route('characters.revisions.voice_preview', $params), [
        'voice_id' => 'multi_female_sophie_v3_uranus_bigtts', 'text' => 'Где ты сейчас?', 'lock_version' => 0,
    ])->assertNotFound();

    Http::assertNothingSent();
})->with(['project', 'series', 'character', 'revision']);
