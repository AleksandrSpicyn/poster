<?php

declare(strict_types=1);

namespace App\Actions\Characters;

use App\Data\Characters\CharacterReferenceData;
use App\Models\CharacterRevision;
use App\Tasks\Characters\UploadCharacterReference;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class UploadCharacterReferenceAction
{
    public function __construct(private UploadCharacterReference $uploadCharacterReference) {}

    public function run(CharacterRevision $revision, CharacterReferenceData $data): Media
    {
        return DB::transaction(fn(): Media => $this->uploadCharacterReference->run($revision, $data));
    }
}
