<?php

declare(strict_types=1);

use App\Actions\Characters\ApproveCharacterRevisionAction;
use App\Actions\Characters\UploadCharacterReferenceAction;
use App\Data\Characters\CharacterReferenceData;
use App\Enums\ReferenceView;
use App\Enums\RevisionStatus;
use App\Models\CharacterRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Tests\Support\serialOperator;

test('утверждение закрепляет внешность и повторяется без новой версии', function (): void {
    Storage::fake('local');
    $revision = CharacterRevision::factory()->create();
    $user = serialOperator();
    app(UploadCharacterReferenceAction::class)->run($revision, new CharacterReferenceData(UploadedFile::fake()->createWithContent('front.png', File::get(base_path('tests/Fixtures/portrait.png'))), ReferenceView::FRONT));
    $action = app(ApproveCharacterRevisionAction::class);

    $approved = $action->run($revision, $user, 1);
    $repeated = $action->run($revision, $user, 1);

    expect($approved->status)->toBe(RevisionStatus::APPROVED);
    expect($approved->lock_version)->toBe(2);
    expect($approved->approved_by)->toBe($user->id);
    expect($approved->character?->current_revision_id)->toBe($approved->id);
    expect($repeated->id)->toBe($approved->id);
    expect($repeated->lock_version)->toBe(2);
});

test('персонаж без референсов не утверждается', function (): void {
    Storage::fake('local');
    $revision = CharacterRevision::factory()->create();
    $user = serialOperator();

    expect(fn() => app(ApproveCharacterRevisionAction::class)->run($revision, $user, 0))
        ->toThrow(ValidationException::class);

    expect($revision->refresh()->status)->toBe(RevisionStatus::DRAFT);
    expect($revision->character?->current_revision_id)->toBeNull();
});
