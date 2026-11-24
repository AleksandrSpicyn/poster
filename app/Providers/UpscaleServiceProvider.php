<?php

declare(strict_types=1);

namespace App\Providers;

use App\Classes\Media\Upscale\ConfiguredVideoUpscalerFactory;
use App\Classes\Media\Upscale\VideoUpscalerFactory;
use Illuminate\Support\ServiceProvider;

class UpscaleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VideoUpscalerFactory::class, ConfiguredVideoUpscalerFactory::class);
    }
}
