<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Data\VideoUsageData;
use Exception;

class VideoGeneratingException extends Exception
{
    public ?VideoUsageData $usage = null;

    public static function withUsage(string $message, VideoUsageData $usage): self
    {
        $exception = new self($message);
        $exception->usage = $usage;

        return $exception;
    }

    protected $message = 'Video generation failed';
}
