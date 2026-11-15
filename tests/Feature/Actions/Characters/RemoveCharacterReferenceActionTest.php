<?php

declare(strict_types=1);

use App\Actions\Characters\RemoveCharacterReferenceAction;
use App\Actions\Characters\UploadCharacterReferenceAction;
use App\Data\Characters\CharacterReferenceData;
use App\Enums\ReferenceView;
use App\Models\CharacterRevision;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

test('удаляет только референс выбранного черновика', function (): void {
    $disk = Storage::fake('local');
    $revision = CharacterRevision::factory()->create();
    $media = app(UploadCharacterReferenceAction::class)->run($revision, new CharacterReferenceData(UploadedFile::fake()->createWithContent('front.png', File::get(base_path('tests/Fixtures/portrait.png'))), ReferenceView::FRONT));
    $path = $media->getPathRelativeToRoot();

    app(RemoveCharacterReferenceAction::class)->run($revision, $media, 1);

    expect($revision->refresh()->lock_version)->toBe(2);
    expect($revision->media()->count())->toBe(0);

    $disk->assertMissing($path);
});

test('чужой референс не удаляется через известный id', function (): void {
    $disk = Storage::fake('local');
    $revision = CharacterRevision::factory()->create();
    $other = CharacterRevision::factory()->create();
    $media = app(UploadCharacterReferenceAction::class)->run($other, new CharacterReferenceData(UploadedFile::fake()->createWithContent('front.png', File::get(base_path('tests/Fixtures/portrait.png'))), ReferenceView::FRONT));

    expect(fn() => app(RemoveCharacterReferenceAction::class)->run($revision, $media, 0))
        ->toThrow(ModelNotFoundException::class);

    expect($revision->refresh()->lock_version)->toBe(0);

    $disk->assertExists($media->getPathRelativeToRoot());
});

test('архивирование блокирует удаление исходных референсов', function (): void {
    $disk = Storage::fake('local');
    $revision = CharacterRevision::factory()->create();
    $media = app(UploadCharacterReferenceAction::class)->run($revision, new CharacterReferenceData(UploadedFile::fake()->createWithContent('front.png', File::get(base_path('tests/Fixtures/portrait.png'))), ReferenceView::FRONT));
    $revision->character()->firstOrFail()->update(['archived_at' => now()]);

    expect(fn() => app(RemoveCharacterReferenceAction::class)->run($revision, $media, 1))
        ->toThrow(ValidationException::class);

    expect($revision->refresh()->lock_version)->toBe(1);

    $disk->assertExists($media->getPathRelativeToRoot());
});
