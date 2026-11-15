<?php

declare(strict_types=1);

namespace App\Classes\Generators\ImageGenerator\Codex;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Providers\ImageProvider;
use Laravel\Ai\Providers\Concerns\GeneratesImages;
use Laravel\Ai\Providers\Concerns\HasImageGateway;
use Laravel\Ai\Providers\Provider;

class CodexProvider extends Provider implements ImageProvider
{
    use GeneratesImages;
    use HasImageGateway;

    public const string IMAGE_MODEL = 'gpt-image-2';

    public function __construct(
        protected Dispatcher $events,
        CodexGateway $gateway,
    ) {
        $this->config = ['name' => 'codex', 'driver' => 'codex', 'key' => ''];
        $this->imageGateway = $gateway;
    }

    public function defaultImageModel(): string
    {
        return self::IMAGE_MODEL;
    }

    /**
     * @param 'low'|'medium'|'high'|null $quality
     * @return array{size: string, quality: 'low'|'medium'|'high'|null}
     */
    public function defaultImageOptions(?string $size = null, ?string $quality = null): array
    {
        return ['size' => $size ?? '9:16', 'quality' => $quality];
    }
}
