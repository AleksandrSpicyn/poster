<?php

declare(strict_types=1);

use App\Models\CharacterRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\serialReferenceMedia;
use function Tests\Support\serialReferenceUpload;
use function Tests\Support\serialVoiceWav;

test('загрузка сохраняет приватный файл с ракурсом и контрольной суммой', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->post(route('characters.references.store', [...$context['params'], 'revision' => $context['revision']]), ['file' => serialReferenceUpload(), 'view' => 'front', 'label' => 'Mira front', 'lock_version' => 0])
        ->assertRedirect();

    $media = $context['revision']->getMedia(CharacterRevision::REFERENCES)->firstOrFail();
    expect($media->disk)->toBe('local');
    expect($media->getCustomProperty('label'))->toBe('Mira front');
    expect($media->getCustomProperty('original_name'))->toBe('front.png');
    expect($media->file_name)->not->toBe('front.png');
    expect($media->getCustomProperty('view'))->toBe('front');
    expect($media->getCustomProperty('checksum'))->toBe(hash_file('sha256', base_path('tests/Fixtures/portrait.png')));
    expect(is_file($media->getPath()))->toBeTrue();
    expect($context['revision']->refresh()->lock_version)->toBe(1);
});

test('загрузка допускает пустую подпись', function (?string $label): void {
    $disk = Storage::fake('local');
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->post(route('characters.references.store', [...$context['params'], 'revision' => $context['revision']]), [
            'file' => serialReferenceUpload(),
            'view' => 'front',
            'label' => $label,
            'lock_version' => 0,
        ])
        ->assertRedirectToRoute('characters.show', $context['params'])
        ->assertSessionHasNoErrors();

    $media = $context['revision']->getMedia(CharacterRevision::REFERENCES)->firstOrFail();
    expect($media->name)->toBe('Front');
    expect($media->getCustomProperty('label'))->toBe('');
    expect($context['revision']->refresh()->lock_version)->toBe(1);

    $disk->assertExists($media->getPathRelativeToRoot());
})->with([
    'пустая строка' => [''],
    'null' => [null],
    'пробелы' => ['   '],
]);

test('слишком длинная подпись не сохраняет референс', function (): void {
    $disk = Storage::fake('local');
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->postJson(route('characters.references.store', [...$context['params'], 'revision' => $context['revision']]), [
            'file' => serialReferenceUpload(),
            'view' => 'front',
            'label' => str_repeat('a', 256),
            'lock_version' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('label');

    expect($context['revision']->media()->count())->toBe(0);
    expect($context['revision']->refresh()->lock_version)->toBe(0);

    $disk->assertEmpty();
});

test('повреждённая картинка не создаёт media', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->postJson(route('characters.references.store', [...$context['params'], 'revision' => $context['revision']]), ['file' => UploadedFile::fake()->createWithContent('bad.png', 'not an image'), 'view' => 'front', 'lock_version' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect($context['revision']->media()->count())->toBe(0);
    Storage::disk('local')->assertEmpty();
});

test('загрузка не прикрепляет файл к чужой версии', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $other = CharacterRevision::factory()->create();

    actingAs(serialOperator())
        ->postJson(route('characters.references.store', [...$context['params'], 'revision' => $other]), ['file' => serialReferenceUpload(), 'view' => 'front', 'lock_version' => 0])
        ->assertNotFound();

    expect($other->media()->count())->toBe(0);
});

test('сохраняет один закрытый образец речи отдельно от внешности', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $url = route('characters.references.store', [...$context['params'], 'revision' => $context['revision']]);
    $file = fn() => UploadedFile::fake()->createWithContent('voice.wav', serialVoiceWav());

    actingAs(serialOperator())->post($url, ['view' => 'voice', 'file' => $file(), 'lock_version' => 0])->assertRedirect();
    $media = $context['revision']->refresh()->getMedia(CharacterRevision::VOICE_SAMPLE)->sole();

    expect($media->disk)->toBe('local')
        ->and($media->getCustomProperty('duration_ms'))->toBe(3000)
        ->and($media->getCustomProperty('checksum'))->toBe(hash_file('sha256', $media->getPath()))
        ->and($context['revision']->getMedia(CharacterRevision::REFERENCES))->toBeEmpty();
    actingAs(serialOperator())->postJson($url, ['view' => 'voice', 'file' => $file(), 'lock_version' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
});

test('замена образца требует совпадения с текущей записью', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $url = route('characters.references.store', [...$context['params'], 'revision' => $context['revision']]);
    $payload = ['view' => 'voice', 'lock_version' => 1, 'file' => UploadedFile::fake()->createWithContent('next.wav', serialVoiceWav(4))];

    actingAs(serialOperator())
        ->post($url, ['view' => 'voice', 'lock_version' => 0, 'file' => UploadedFile::fake()->createWithContent('voice.wav', serialVoiceWav())])
        ->assertSessionHasNoErrors();
    $previous = $context['revision']->media()->firstOrFail();

    postJson($url, [...$payload, 'replace_voice_id' => $previous->id + 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect($context['revision']->media()->firstOrFail()->id)->toBe($previous->id);
    expect(is_file($previous->getPath()))->toBeTrue();

    post($url, [...$payload, 'replace_voice_id' => $previous->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $current = $context['revision']->media()->sole();
    expect($current->id)->not->toBe($previous->id);
    expect($current->getCustomProperty('duration_ms'))->toBe(4000);
    expect(is_file($previous->getPath()))->toBeFalse();
});

test('ошибка записи нового файла оставляет прежний голос доступным', function (): void {
    $disk = Storage::fake('local');
    $context = serialCharacterContext();
    $url = route('characters.references.store', [...$context['params'], 'revision' => $context['revision']]);
    $file = fn(int $seconds): UploadedFile => UploadedFile::fake()->createWithContent('voice.wav', serialVoiceWav($seconds));
    actingAs(serialOperator())->post($url, ['view' => 'voice', 'file' => $file(3), 'lock_version' => 0])
        ->assertSessionHasNoErrors();
    $previous = $context['revision']->media()->sole();
    Event::listen(MediaHasBeenAddedEvent::class, static function (): never {
        throw new RuntimeException('Ошибка обработки нового образца');
    });

    postJson($url, ['view' => 'voice', 'file' => $file(4), 'replace_voice_id' => $previous->id, 'lock_version' => 1])
        ->assertInternalServerError();

    expect($context['revision']->refresh()->lock_version)->toBe(1)
        ->and($context['revision']->media()->sole()->id)->toBe($previous->id)
        ->and(hash_file('sha256', $previous->getPath()))->toBe($previous->getCustomProperty('checksum'))
        ->and($disk->allFiles())->toBe([$previous->getPathRelativeToRoot()]);
});

test('другая вкладка изменила черновик во время прослушивания', function (bool $approve): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $params = [...$context['params'], 'revision' => $context['revision']];
    $url = route('characters.references.store', $params);
    serialReferenceMedia($context['revision']);
    actingAs(serialOperator())->post($url, ['view' => 'voice', 'lock_version' => 0, 'file' => UploadedFile::fake()->createWithContent('old.wav', serialVoiceWav())])
        ->assertSessionHasNoErrors();
    $media = $context['revision']->refresh()->getFirstMedia(CharacterRevision::VOICE_SAMPLE) ?? throw new RuntimeException('Missing voice.');

    if ($approve) {
        post(route('characters.revisions.approve', $params), ['lock_version' => 1])->assertSessionHasNoErrors();
    } else {
        post($url, ['view' => 'front', 'lock_version' => 1, 'file' => serialReferenceUpload()])->assertSessionHasNoErrors();
    }

    postJson($url, ['view' => 'voice', 'lock_version' => 1, 'replace_voice_id' => $media->id, 'file' => UploadedFile::fake()->createWithContent('next.wav', serialVoiceWav(4))])
        ->assertUnprocessable()->assertJsonValidationErrors('lock_version');

    expect($context['revision']->refresh()->getMedia(CharacterRevision::VOICE_SAMPLE)->sole()->id)->toBe($media->id);
    expect(hash_file('sha256', $media->getPath()))->toBe($media->getCustomProperty('checksum'));
})->with([true, false]);

test('запись голоса требует версии даже у нового черновика', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();

    actingAs(serialOperator())->postJson(route('characters.references.store', [...$context['params'], 'revision' => $context['revision']]), [
        'view' => 'voice', 'file' => UploadedFile::fake()->createWithContent('sample.wav', serialVoiceWav()),
    ])->assertUnprocessable()->assertJsonValidationErrors('lock_version');

    expect($context['revision']->media()->exists())->toBeFalse();
});

test('после утверждения доступны только голос и только у текущей версии', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $revision = $context['revision'];
    serialReferenceMedia($revision);
    $params = [...$context['params'], 'revision' => $revision];
    actingAs(serialOperator())
        ->post(route('characters.revisions.approve', $params), ['lock_version' => 0])
        ->assertSessionHasNoErrors();

    postJson(route('characters.references.store', $params), [
        'view' => 'front', 'file' => serialReferenceUpload(), 'lock_version' => $revision->refresh()->lock_version,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('revision');

    $next = CharacterRevision::factory()->for($context['character'])->create(['number' => $revision->number + 1]);
    serialReferenceMedia($next);
    post(route('characters.revisions.approve', [...$params, 'revision' => $next]), ['lock_version' => 0])
        ->assertSessionHasNoErrors();

    postJson(route('characters.references.store', $params), [
        'view' => 'voice', 'file' => UploadedFile::fake()->createWithContent('sample.wav', serialVoiceWav()), 'lock_version' => $revision->lock_version,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('revision');
    expect($revision->refresh()->getMedia(CharacterRevision::VOICE_SAMPLE))->toBeEmpty();
});
