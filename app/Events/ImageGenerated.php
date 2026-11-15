<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Сообщает о готовности первого кадра; URL ещё не является собранным роликом.
 */
class ImageGenerated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(private readonly string $imageUrl) {}

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }
}
