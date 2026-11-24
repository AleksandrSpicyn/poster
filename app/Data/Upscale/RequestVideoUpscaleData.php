<?php

declare(strict_types=1);

namespace App\Data\Upscale;

use App\Enums\UpscaleExport;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class RequestVideoUpscaleData extends Data
{
    public function __construct(public UpscaleExport $export, #[Min(1)] public ?int $retry_operation_id = null) {}
}
