<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Classes\Generators\ImageGenerator\ImageGenerator;
use App\Data\ImageReferenceData;

/**
 * Добавляет к идее общий визуальный стиль перед обращением к ImageGenerator.
 */
readonly class CreateImage
{
    public function __construct(private ImageGenerator $imageGenerator) {}

    /** @param list<ImageReferenceData> $references */
    public function run(string $idea, array $references = []): string
    {
        $prompt = CreateImagePrompt::run($idea);

        return $this->imageGenerator->generate($prompt, $references);
    }
}
