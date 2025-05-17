<?php

namespace App\Classes\Generators\ImageGenerator;

interface ImageGenerator
{
    public function generate(string $prompt): string;
}
