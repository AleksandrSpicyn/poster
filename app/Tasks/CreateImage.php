<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Classes\Generators\ImageGenerator\ImageGenerator;

readonly class CreateImage
{
    public function __construct(private ImageGenerator $imageGenerator) {}

    public function run(string $idea): string
    {
        $prompt = CreateImagePrompt::run($idea);

        return $this->imageGenerator->generate($prompt);
    }
}
