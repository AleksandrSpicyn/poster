<?php

namespace App\Classes\Generators\PromptGenerator;

interface PromptGenerator
{
    public function generate(string $prompt): string;
}
