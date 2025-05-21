<?php

namespace App\Classes\Publishers;

use App\Models\Prompt;

interface Publisher
{
    public function publish(Prompt $prompt): bool;
}
