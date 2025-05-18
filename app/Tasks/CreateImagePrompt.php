<?php

namespace App\Tasks;

class CreateImagePrompt extends Task
{
    public const DALLE_STYLE_TAGS = [
        'photo-realistic',
        'natural lighting',
        'sharp focus',
        '8k resolution',
        'realistic shadows',
        'depth of field',
        'ultra detailed',
        'cinematic lighting',
        'highly aesthetic',
        'vibrant colors',
        'fantasy',
        'vertical composition',
        '9:16 aspect ratio',
    ];

    public static function run(string $idea): string
    {
        $style = implode(', ', self::DALLE_STYLE_TAGS);

        return "Idea: $idea, Styles: $style";
    }
}
