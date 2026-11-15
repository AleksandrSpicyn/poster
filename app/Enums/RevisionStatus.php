<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum RevisionStatus: string
{
    use EnumTrait;

    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case SUPERSEDED = 'superseded';
}
