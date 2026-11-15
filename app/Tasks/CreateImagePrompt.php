<?php

declare(strict_types=1);

namespace App\Tasks;

/**
 * Задаёт единый стиль вертикального изображения для конвейера коротких видео.
 */
class CreateImagePrompt
{
    public const array DALLE_STYLE_TAGS = [
        'photo-realistic',
        'natural lighting',
        'sharp focus',
        'realistic shadows',
        'depth of field',
        'lifelike surface textures',
        'cinematic lighting',
        'highly aesthetic',
        'restrained natural colors',
        'vertical composition',
        '9:16 aspect ratio',
    ];

    public static function run(string $idea): string
    {
        $style = implode(', ', self::DALLE_STYLE_TAGS);

        return <<<TEXT
Idea: $idea, Styles: $style

Purpose and scene: Create a single photographic opening frame for a vertical short video, just before the main action begins. Show the setup, not the completed payoff. One coherent photographic scene, one main subject, and only the object needed for the interaction. The surprising relationship between subject and setting is immediately readable at phone size.

Composition: Use an eye-level medium-wide composition with clear subject separation. Keep the face and interaction in the central area, comfortably away from the top, bottom, and right edges used by social app controls. Leave room for the intended movement; keep essential anatomy and the interacting object fully inside the frame.

Lighting and materials: Use a single motivated light source, believable contact shadows, and natural surface detail. Keep texture visible in highlights and shadows; avoid a plastic, oversharpened appearance.

Constraints: Preserve coherent anatomy and believable scale. The location is recognizable through a few specific environmental cues rather than clutter. A clean photographic frame without lettering, captions, logos, borders, or watermarks.
TEXT;
    }
}
