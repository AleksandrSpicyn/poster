<?php

declare(strict_types=1);

namespace App\Classes\Generators\Codex;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Providers\ImageProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Providers\Concerns\GeneratesImages;
use Laravel\Ai\Providers\Concerns\GeneratesText;
use Laravel\Ai\Providers\Concerns\HasImageGateway;
use Laravel\Ai\Providers\Concerns\HasTextGateway;
use Laravel\Ai\Providers\Concerns\StreamsText;
use Laravel\Ai\Providers\Provider;

class CodexProvider extends Provider implements ImageProvider, TextProvider
{
    use GeneratesImages;
    use GeneratesText;
    use HasImageGateway;
    use HasTextGateway;
    use StreamsText;

    public const string IMAGE_MODEL = 'gpt-image-2';

    /** @param array{name: string, driver: string, key: string, model: string} $config */
    public function __construct(
        protected array $config,
        protected Dispatcher $events,
        CodexTextGateway $textGateway,
        CodexImageGateway $imageGateway,
    ) {
        $this->textGateway = $textGateway;
        $this->imageGateway = $imageGateway;
    }

    public function defaultTextModel(): string
    {
        return $this->config['model'];
    }

    public function cheapestTextModel(): string
    {
        return $this->defaultTextModel();
    }

    public function smartestTextModel(): string
    {
        return $this->defaultTextModel();
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
