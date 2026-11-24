<?php

declare(strict_types=1);

namespace App\Classes\Media\Upscale;

use App\Enums\UpscaleProvider;

interface VideoUpscalerFactory
{
    public function primary(): UpscaleProvider;

    public function make(UpscaleProvider $provider): VideoUpscaler;
}
