<?php

declare(strict_types=1);

namespace App\Classes\Media\Upscale;

use App\Enums\UpscaleProvider;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final readonly class ConfiguredVideoUpscalerFactory implements VideoUpscalerFactory
{
    /** @param array<string, class-string> $drivers */
    public function __construct(
        private Container $container,
        #[Config('services.upscale.drivers')] private array $drivers,
        #[Config('services.upscale.provider')] private string $provider,
    ) {}

    public function primary(): UpscaleProvider
    {
        return UpscaleProvider::tryFrom($this->provider) ?? throw new InvalidArgumentException('Unknown upscale provider.');
    }

    public function make(UpscaleProvider $provider): VideoUpscaler
    {
        $class = $this->drivers[$provider->value] ?? null;

        if ($class === null || ! is_a($class, VideoUpscaler::class, true)) {
            throw new InvalidArgumentException('Invalid upscale driver registration.');
        }

        $driver = $this->container->make($class);

        if (! $driver instanceof VideoUpscaler) {
            throw new InvalidArgumentException('Resolved driver must implement VideoUpscaler.');
        }

        return $driver;
    }
}
