<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Support\Carbon;
use RuntimeException;

final class FacebookRateLimitException extends RuntimeException
{
    public function __construct(public readonly Carbon $retryAt)
    {
        parent::__construct('Facebook ограничил частоту запросов до ' . $retryAt->toIso8601String());
    }
}
