<?php

namespace App\Classes\Generators\VideoGenerator;

interface VideoGenerator
{
    public function generate(string $imageUrl, string $prompt): string;
    public function check(string $id): ?string;
}
