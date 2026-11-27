<?php

declare(strict_types=1);

namespace App\Data\Series;

use App\Enums\SeriesAssetKind;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class SeriesAssetData extends Data
{
    public function __construct(
        #[Min(1), Max(150)] public string $label,
        public SeriesAssetKind $kind,
        #[File, Max(10240)] public UploadedFile $file,
    ) {}
}
