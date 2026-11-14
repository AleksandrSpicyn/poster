<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class VideoGeneratingException extends Exception
{
    protected $message = 'Video generation failed';
}
