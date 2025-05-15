<?php

namespace App\Tasks;

use App\Classes\ChatGpt;

class CreateImage
{
    public function run(string $idea): string
    {
        $prompt = CreateImagePrompt::run($idea);

        return ChatGpt::createImage($prompt);
    }
}
