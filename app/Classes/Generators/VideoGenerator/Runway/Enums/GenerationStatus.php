<?php

namespace App\Classes\Generators\VideoGenerator\Runway\Enums;

enum GenerationStatus: string
{
    case PENDING = 'PENDING';
    case THROTTLED = 'THROTTLED';
    case RUNNING = 'RUNNING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';
}
