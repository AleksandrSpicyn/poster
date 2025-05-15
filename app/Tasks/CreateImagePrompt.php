<?php

namespace App\Tasks;

class CreateImagePrompt
{
    public const DALLE_STYLE_TAGS = [
        'ultra detailed',
        'cinematic lighting',
        'highly aesthetic',
        '4k',
        'vibrant colors',
        'surreal',
        'fantasy',
        'vertical composition',
        '9:16 aspect ratio',
    ];

    public static function run(string $idea): string
    {
        $style = implode(', ', self::DALLE_STYLE_TAGS);

        return "$idea, $style";
    }
}
