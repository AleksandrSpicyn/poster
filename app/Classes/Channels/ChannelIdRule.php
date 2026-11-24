<?php

declare(strict_types=1);

namespace App\Classes\Channels;

use Spatie\LaravelData\Attributes\Validation\Rule;

final class ChannelIdRule extends Rule
{
    public function __construct()
    {
        parent::__construct('integer', 'min:1', 'distinct');
    }
}
