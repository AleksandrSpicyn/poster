<?php

declare(strict_types=1);

namespace App\Data\Projects;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Image;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Mimes;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class ProjectCoverData extends Data
{
    public function __construct(
        #[Min(0), Max(100)] public int $x,
        #[Min(0), Max(100)] public int $y,
        #[Min(1)] public ?int $base_id = null,
        #[File, Image, Mimes('jpeg', 'jpg', 'png', 'webp'), Max(10240)] public ?UploadedFile $file = null,
    ) {}
}
