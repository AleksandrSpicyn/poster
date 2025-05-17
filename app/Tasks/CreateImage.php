<?php

namespace App\Tasks;

use App\Classes\Generators\ImageGenerator\ImageGenerator;

class CreateImage extends Task
{
    public function __construct(private readonly ImageGenerator $imageGenerator)
    {
    }

    public function run(string $idea): string
    {
        $prompt = CreateImagePrompt::run($idea);

        return $this->imageGenerator->generate($prompt);
    }
}
