<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class PublicationProcessingException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $inbox = false)
    {
        parent::__construct($message);
    }
}
