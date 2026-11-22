<?php

declare(strict_types=1);

namespace App\Data\Characters;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Image;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Mimes;

class CreateCharacterRevisionData extends CharacterRevisionData
{
    #[File, Image, Mimes('png', 'jpg', 'jpeg', 'webp'), Max(CharacterReferenceData::MAX_SIZE_KB)]
    public ?UploadedFile $file = null;
}
