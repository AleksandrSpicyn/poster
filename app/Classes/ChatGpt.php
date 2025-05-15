<?php

namespace App\Classes;

use OpenAI\Laravel\Facades\OpenAI;

/* TODO Пересадить на Interface и Abstract */
class ChatGpt
{
    public static function createPrompt(string $prompt): string
    {
        $result = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        return trim($result->choices[0]->message->content);
    }

    public static function createImage(string $prompt): string
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
