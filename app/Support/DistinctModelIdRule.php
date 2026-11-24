<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\LaravelData\Attributes\Validation\Rule;

final class DistinctModelIdRule extends Rule
{
    public function __construct()
    {
        parent::__construct('integer', 'min:1', 'distinct');
    }
}
