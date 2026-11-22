<?php

declare(strict_types=1);

use App\Models\CharacterRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

use function Pest\Laravel\actingAs;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\serialReferenceUpload;

test('новая версия создаётся черновиком и не меняет текущую внешность', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->post(route('characters.revisions.store', $context['params']), ['description' => 'Mira in a blue coat', 'appearance' => ['coat' => 'blue'], 'voice' => ['tone' => 'calm']])
        ->assertRedirect();

    $revision = $context['character']->revisions()->orderByDesc('number')->firstOrFail();
    expect($revision->description)->toBe('Mira in a blue coat');
    expect($revision->appearance)->toBe(['coat' => 'blue']);
    expect($revision->status->value)->toBe('draft');
    expect($context['character']->refresh()->current_revision_id)->toBeNull();
});

test('пустое описание не создаёт версию', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->postJson(route('characters.revisions.store', $context['params']), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('description');

    expect($context['character']->revisions()->count())->toBe(1);
});

test('название и первый референс сохраняются вместе с черновиком', function (): void {
    $disk = Storage::fake('local');
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->post(route('characters.revisions.store', $context['params']), [
            'title' => 'Почтальон в дождевике',
            'description' => 'Тёмный дождевик',
            'file' => serialReferenceUpload(),
        ])
        ->assertRedirectToRoute('characters.show', $context['params'])
        ->assertSessionHasNoErrors();

    $revision = $context['character']->revisions()->latest('number')->firstOrFail();
    $reference = $revision->getMedia(CharacterRevision::REFERENCES)->sole();
    expect($revision->title)->toBe('Почтальон в дождевике');
    expect($reference)->not->toBeNull();

    $disk->assertExists($reference->getPathRelativeToRoot());
});

test('недопустимые размеры изображения отменяют создание черновика', function (): void {
    $disk = Storage::fake('local');
    $context = serialCharacterContext();
    $count = $context['character']->revisions()->count();

    actingAs(serialOperator())
        ->postJson(route('characters.revisions.store', $context['params']), [
            'title' => 'Маленький портрет',
            'description' => 'Тёмный дождевик',
            'file' => UploadedFile::fake()->createWithContent('tiny.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC', true)),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect($context['character']->revisions()->count())->toBe($count);
    $disk->assertEmpty();
});

test('ошибка после записи изображения удаляет файл и откатывает версию', function (): void {
    $disk = Storage::fake('local');
    $context = serialCharacterContext();
    $count = $context['character']->revisions()->count();
    Event::listen(MediaHasBeenAddedEvent::class, static function (): never {
        throw new RuntimeException('Ошибка обработки изображения');
    });

    actingAs(serialOperator())
        ->post(route('characters.revisions.store', $context['params']), [
            'title' => 'Почтальон',
            'description' => 'Курьер в дождевике',
            'file' => serialReferenceUpload(),
        ])
        ->assertServerError();

    expect($context['character']->revisions()->count())->toBe($count);
    $disk->assertEmpty();
});
