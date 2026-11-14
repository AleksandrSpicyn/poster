<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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
