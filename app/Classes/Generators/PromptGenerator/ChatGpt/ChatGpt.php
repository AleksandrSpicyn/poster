<?php

namespace App\Classes\Generators\PromptGenerator\ChatGpt;

use App\Classes\Generators\PromptGenerator\AbstractPromptGenerator;
use OpenAI\Laravel\Facades\OpenAI;

class ChatGpt extends AbstractPromptGenerator
{
    public function generate(string $prompt): string
    {
        $result = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        return trim($result->choices[0]->message->content);
    }
}
