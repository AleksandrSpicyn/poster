<?php

declare(strict_types=1);

namespace App\Data\Characters;

use App\Enums\ReferenceView;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Mimes;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CharacterReferenceData extends Data
{
    public const int MAX_SIZE_KB = 10240;

    public const int MAX_REFERENCES = 30;

    public function __construct(
        #[Required, File, Mimes('png', 'jpg', 'jpeg', 'webp', 'wav', 'mp3'), Max(self::MAX_SIZE_KB)]
        public UploadedFile $file,
        public ReferenceView $view,
        #[Max(255)]
        public ?string $label = null,
        #[Min(0)]
        public ?int $lock_version = null,
        #[Min(1)]
        public ?int $replace_voice_id = null,
    ) {}
}
