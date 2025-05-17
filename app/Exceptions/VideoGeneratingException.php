<?php

namespace App\Exceptions;

use Exception;

class VideoGeneratingException extends Exception
{
    protected $message = 'Video generation failed';
}
