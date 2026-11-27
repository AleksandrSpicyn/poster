<?php

declare(strict_types=1);

namespace App\Data\Shots;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class ApproveOpeningFrameData extends Data
{
    public function __construct(#[Min(1)] public ?int $asset_id = null) {}
}
