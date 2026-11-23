<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\VideoReceiptStatus;

final readonly class VideoUsageData
{
    public function __construct(
        public VideoReceiptStatus $status,
        public ?int $completionTokens = null,
        public ?string $resolution = null,
        public ?string $model = null,
        public ?int $duration = null,
    ) {}
}
