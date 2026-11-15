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

Purpose and visual hook: Create the photographic first frame of the story, at the instant its opening problem becomes urgent. Show the subject's goal, the obstacle, and the direction of the impending action together. Use an active, off-balance pose, a readable expression, and the relevant prop already poised to move. The viewer must understand what is at stake immediately. Show the unresolved hook, not the completed payoff; avoid a calm scenic portrait or an animal simply posing beside an object.

Composition: Output a portrait PNG at least 720 pixels wide and 1280 pixels high. Use a medium-wide action composition with clear subject separation and a visible path for the movement. Keep the face, goal, obstacle, and action in the central area, comfortably away from the top, bottom, and right edges used by social app controls. Keep essential anatomy and the relevant props fully inside the frame and in focus. Use one main subject and at most two relevant props in one coherent location.

Lighting and materials: Use a single motivated light source, believable contact shadows, and natural surface detail. Keep texture visible in highlights and shadows; avoid a plastic, oversharpened appearance.

Constraints: Preserve coherent anatomy and believable scale. The location is recognizable through a few specific environmental cues rather than clutter. A clean photographic frame without lettering, captions, logos, borders, or watermarks.
TEXT;
    }
}
