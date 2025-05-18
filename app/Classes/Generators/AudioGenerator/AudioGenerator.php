<?php

namespace App\Classes\Generators\AudioGenerator;

interface AudioGenerator
{
    public function generate(string $title, string $prompt): string;
}
