<?php

declare(strict_types=1);

namespace App\Data\TiktokDemo;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\Accepted;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\MimeTypes;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class DirectPostData extends Data
{
    public function __construct(
        #[Required, MimeTypes('video/mp4'), Max(960)] public UploadedFile $video,
        #[Required, Max(64)] public string $binding,
        #[Required, Max(32)] public string $privacy,
        #[Accepted] public bool $consent,
        #[Max(2200)] public ?string $title = null,
        public bool $comment = false,
        public bool $duet = false,
        public bool $stitch = false,
        public bool $disclose = false,
        public bool $own_brand = false,
        public bool $partner = false,
        public bool $ai = false,
    ) {}
}
