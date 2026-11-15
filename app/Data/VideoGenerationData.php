<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\VideoAudioSource;
use App\Enums\VideoProvider;
use Spatie\LaravelData\Data;

/**
 * Возвращает внешний ID и фактического провайдера для сохранения перед polling.
 */
class VideoGenerationData extends Data
{
    public function __construct(
        public string $id,
        public VideoProvider $provider,
        public VideoAudioSource $audioSource,
        public ?VideoGenerationRequestData $request = null,
    ) {}
}
