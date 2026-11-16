<?php

declare(strict_types=1);

namespace App\Data\Channels;

use App\Enums\ChannelType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ChannelData extends Data
{
    public function __construct(
        #[Required, Max(255)] public string $name,
        public ChannelType $type,
        #[Max(255)] public ?string $client_id = null,
        #[Max(2048)] public ?string $client_secret = null,
        public ?bool $is_active = null,
    ) {}
}
