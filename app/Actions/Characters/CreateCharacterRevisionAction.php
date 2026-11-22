<?php

declare(strict_types=1);

namespace App\Actions\Characters;

use App\Data\Characters\CharacterReferenceData;
use App\Data\Characters\CreateCharacterRevisionData;
use App\Enums\ReferenceView;
use App\Models\Character;
use App\Tasks\Characters\SaveCharacterRevision;
use App\Tasks\Characters\UploadCharacterReference;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

readonly class CreateCharacterRevisionAction
{
    public function __construct(
        private SaveCharacterRevision $saveRevision,
        private UploadCharacterReference $uploadReference,
    ) {}

    public function run(Character $character, CreateCharacterRevisionData $data): void
    {
        $media = null;

        try {
            DB::transaction(function () use ($character, $data, &$media): void {
                $revision = $this->saveRevision->run($character, $data);

                if ($data->file instanceof UploadedFile) {
                    $media = $this->uploadReference->run($revision, new CharacterReferenceData($data->file, ReferenceView::OTHER));
                }
            });
        } catch (Throwable $exception) {
            if ($media instanceof Media) {
                $this->uploadReference->discardFiles($media);
            }

            throw $exception;
        }
    }
}
