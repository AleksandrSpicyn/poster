<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator;

use App\Enums\VideoProvider;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

readonly class VideoGeneratorFactory
{
    /** @param array<string, class-string<VideoGenerator>> $drivers */
    public function __construct(
        private Container $container,
        #[Config('services.video.drivers')] private array $drivers,
        #[Config('services.video.provider')] private string $provider,
        #[Config('services.video.fallback_provider')] private string $fallbackProvider,
    ) {}

    public function make(VideoProvider $provider): VideoGenerator
    {
        $driver = $this->drivers[$provider->value] ?? throw new InvalidArgumentException('Video driver is not registered.');

        $instance = $this->container->make($driver);

        if (! $instance instanceof VideoGenerator) {
            throw new InvalidArgumentException('Registered video driver must implement VideoGenerator.');
        }

        return $instance;
    }

    public function primary(): VideoProvider
    {
        return VideoProvider::from($this->provider);
    }

    public function fallback(): VideoProvider
    {
        return VideoProvider::from($this->fallbackProvider);
    }
}
