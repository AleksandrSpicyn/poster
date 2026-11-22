<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ClippingProviderException extends RuntimeException
{
    public function __construct(string $endpoint, public readonly int $status, public readonly int $retryAfter)
    {
        parent::__construct("OpusClip $endpoint returned HTTP $status.");
    }
}
