<?php

declare(strict_types=1);

namespace App\Classes\Generators\ImageGenerator;

use App\Classes\Generators\ImageGenerator\ChatGpt\ChatGpt;
use Illuminate\Container\Attributes\Bind;

#[Bind(ChatGpt::class)]
interface ImageGenerator
{
    public function generate(string $prompt): string;
}
