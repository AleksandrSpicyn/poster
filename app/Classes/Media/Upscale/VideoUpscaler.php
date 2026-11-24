<?php

declare(strict_types=1);

namespace App\Classes\Media\Upscale;

use App\Data\Upscale\UpscaleCapabilitiesData;
use App\Data\Upscale\UpscaleReceiptData;
use App\Data\Upscale\UpscaleRequestData;
use App\Data\Upscale\UpscaleStatusData;

interface VideoUpscaler
{
    public function capabilities(): UpscaleCapabilitiesData;

    public function submit(UpscaleRequestData $request): UpscaleReceiptData;

    public function check(UpscaleReceiptData $receipt): UpscaleStatusData;
}
