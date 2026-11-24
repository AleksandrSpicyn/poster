<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum UpscaleOperationStatus: string
{
    use EnumTrait;

    case PENDING = 'pending';
    case SUBMITTING = 'submitting';
    case SUBMISSION_UNKNOWN = 'submission_unknown';
    case SUBMITTED = 'submitted';
    case PROCESSING = 'processing';
    case DOWNLOADING = 'downloading';
    case READY = 'ready';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
