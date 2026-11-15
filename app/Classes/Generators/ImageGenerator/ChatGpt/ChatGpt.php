<?php

declare(strict_types=1);

namespace App\Classes\Generators\ImageGenerator\ChatGpt;

use App\Classes\Generators\ImageGenerator\ImageGenerator;
use App\Data\ImageReferenceData;
use InvalidArgumentException;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * Адаптер OpenAI Images: получает URL вертикального изображения для первого кадра.
 */
class ChatGpt implements ImageGenerator
{
    /** @param list<ImageReferenceData> $references */
    public function generate(string $prompt, array $references = []): string
    {
        if ($references !== []) {
            throw new InvalidArgumentException('The DALL-E image adapter does not support reference images.');
        }

        $response = OpenAI::images()->create([
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1792',
            'response_format' => 'url',
        ]);

        return $response->data[0]->url;
    }
}
