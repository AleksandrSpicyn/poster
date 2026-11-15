<?php

declare(strict_types=1);

use App\Actions\Characters\UploadCharacterReferenceAction;
use App\Data\Characters\CharacterReferenceData;
use App\Enums\ReferenceView;
use App\Models\CharacterRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

test('сохраняет приватный референс с ракурсом и контрольной суммой', function (): void {
    $disk = Storage::fake('local');
    Storage::fake('public');
    $revision = CharacterRevision::factory()->create();
    $file = UploadedFile::fake()->createWithContent('front.png', File::get(base_path('tests/Fixtures/portrait.png')));
    $checksum = hash_file('sha256', $file->getPathname());

    $media = app(UploadCharacterReferenceAction::class)->run($revision, new CharacterReferenceData($file, ReferenceView::FRONT, 'Face'));

    expect($media->disk)->toBe('local');
    expect($media->getCustomProperty('checksum'))->toBe($checksum);
    expect($media->getCustomProperty('view'))->toBe('front');
    expect($revision->refresh()->lock_version)->toBe(1);

    $disk->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertEmpty();
});

test('не добавляет референс к утвержденной версии', function (): void {
    $disk = Storage::fake('local');
    $revision = CharacterRevision::factory()->approved()->create();
    $data = new CharacterReferenceData(UploadedFile::fake()->createWithContent('front.png', File::get(base_path('tests/Fixtures/portrait.png'))), ReferenceView::FRONT);

    expect(fn() => app(UploadCharacterReferenceAction::class)->run($revision, $data))
        ->toThrow(ValidationException::class);

    $disk->assertEmpty();
    expect($revision->media()->count())->toBe(0);
});

test('отклоняет файл без изображения до записи медиа', function (): void {
    $disk = Storage::fake('local');
    $revision = CharacterRevision::factory()->create();
    $data = new CharacterReferenceData(UploadedFile::fake()->createWithContent('fake.png', 'not an image'), ReferenceView::FRONT);

    expect(fn() => app(UploadCharacterReferenceAction::class)->run($revision, $data))
        ->toThrow(ValidationException::class);

    $disk->assertEmpty();
    expect($revision->refresh()->lock_version)->toBe(0);
});

test('отклоняет анимированный PNG до сохранения референса', function (): void {
    $disk = Storage::fake('local');
    $revision = CharacterRevision::factory()->create();
    $original = File::get(base_path('tests/Fixtures/portrait.png'));
    $animation = pack('N2', 2, 0);
    $chunk = pack('N', strlen($animation)) . 'acTL' . $animation . pack('N', crc32('acTL' . $animation));
    $file = UploadedFile::fake()->createWithContent('animated.png', substr($original, 0, 33) . $chunk . substr($original, 33));

    expect(fn() => app(UploadCharacterReferenceAction::class)->run($revision, new CharacterReferenceData($file, ReferenceView::FRONT)))
        ->toThrow(ValidationException::class);

    $disk->assertEmpty();
    expect($revision->refresh()->lock_version)->toBe(0);
});

test('устаревшая загрузка не добавляет второй файл', function (): void {
    Storage::fake('local');
    $revision = CharacterRevision::factory()->create();
    $action = app(UploadCharacterReferenceAction::class);
    $action->run($revision, new CharacterReferenceData(UploadedFile::fake()->createWithContent('front.png', File::get(base_path('tests/Fixtures/portrait.png'))), ReferenceView::FRONT));

    $stale = new CharacterReferenceData(UploadedFile::fake()->createWithContent('profile.png', File::get(base_path('tests/Fixtures/portrait.png'))), ReferenceView::PROFILE);

    expect(fn() => $action->run($revision, $stale))->toThrow(ValidationException::class);

    expect($revision->media()->count())->toBe(1);
    expect($revision->refresh()->lock_version)->toBe(1);
});
