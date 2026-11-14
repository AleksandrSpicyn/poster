<?php

declare(strict_types=1);

namespace App\Classes\Generators\AudioGenerator;

use App\Classes\Generators\AudioGenerator\SunoUnofficial\SunoUnofficial;
use Illuminate\Container\Attributes\Bind;

#[Bind(SunoUnofficial::class)]
interface AudioGenerator
{
    public function generate(string $title, string $prompt): string;
}
