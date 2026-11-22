<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum PublicationStatus: string
{
    use EnumTrait;

    case PLANNED = 'planned';
    case SUBMITTING = 'submitting';
    case PROCESSING = 'processing';
    case PUBLISHING_CONTAINER = 'publishing_container';
    case INBOX = 'inbox';
    case PUBLISHED = 'published';
    case SUBMISSION_UNKNOWN = 'submission_unknown';
    case FAILED = 'failed';
}
