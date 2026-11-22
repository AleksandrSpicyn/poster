<?php

declare(strict_types=1);

namespace App\Data\Tiktok;

use App\Enums\TiktokDeliveryMode;
use Spatie\LaravelData\Attributes\Validation\Accepted;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\RequiredIf;
use Spatie\LaravelData\Data;

final class TiktokPublicationData extends Data
{
    public function __construct(
        public int $media_id,
        #[Max(255)] public string $account_id,
        public TiktokDeliveryMode $mode,
        #[Accepted] public bool $consent,
        #[Max(2200)] public ?string $title = null,
        #[RequiredIf('mode', 'direct'), Max(32)] public ?string $privacy = null,
        public bool $comment = false,
        public bool $duet = false,
        public bool $stitch = false,
        public bool $disclose = false,
        public bool $own_brand = false,
        public bool $partner = false,
        public bool $ai = false,
    ) {}
}
