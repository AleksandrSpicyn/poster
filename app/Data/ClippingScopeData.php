<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ClippingScopeData extends Data
{
    public function __construct(
        #[Required] public bool $import_projects,
        #[Required] public bool $import_accounts,
        #[Required] public bool $import_archives,
        #[Min(1)] public ?int $project_id = null,
    ) {}
}
