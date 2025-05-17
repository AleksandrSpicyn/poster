<?php

namespace App\Classes\Publishers;

interface Publisher
{
    public function publish(string $url): bool;
}
