<?php

declare(strict_types=1);

use App\Models\CharacterRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\serialReferenceUpload;

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
