<?php

declare(strict_types=1);

namespace App\Classes\Generators\PromptGenerator\ChatGpt;

use App\Classes\Generators\PromptGenerator\PromptGenerator;
use App\Support\Integrations\Payload;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * Адаптер OpenAI Chat: извлекает непустой текст идеи из ответа модели.
 */
class ChatGpt implements PromptGenerator
{
    public function generate(string $prompt): string
    {
        $result = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        return trim(Payload::string($result->choices[0]->message->content, 'choices.0.message.content'));
    }
}
