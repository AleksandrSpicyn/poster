<?php

declare(strict_types=1);

namespace App\Classes\Publishers;

interface Publisher
{
    public function publish(): bool;
}
