<?php

namespace App\Classes\Publishers;

use App\Models\Channel;

abstract class AbstractPublisher implements Publisher
{
    public function __construct(protected readonly Channel $channel)
    {
    }
}
