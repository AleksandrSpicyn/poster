<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator;

use App\Classes\Generators\VideoGenerator\Hailuo\Hailuo;
use Illuminate\Container\Attributes\Bind;

#[Bind(Hailuo::class)]
interface VideoGenerator
{
    public function generate(string $imageUrl, string $prompt): string;

    public function check(string $id): ?string;
}
