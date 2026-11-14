<?php

declare(strict_types=1);

namespace App\Classes\Generators\ImageGenerator\ChatGpt;

use App\Classes\Generators\ImageGenerator\ImageGenerator;
use OpenAI\Laravel\Facades\OpenAI;

class ChatGpt implements ImageGenerator
{
    public function generate(string $prompt): string
    {
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
