<?php

namespace App\Tasks;

use App\Classes\Generators\VideoGenerator\VideoGenerator;

class CreateVideo extends Task
{
    public function __construct(private readonly VideoGenerator $videoGenerator)
    {
    }

    public function run(string $imageUrl, string $idea): string
    {
        $prompt = <<<TEXT
This video is based on the following concept: "{$idea}".

Animate a vivid, lifelike scene where the animal naturally interacts with its environment — moving its head, blinking, shifting weight, reacting to sounds or light. Avoid static posing or looping motions; gestures should feel organic and curious.

Bring the surroundings to life: add wind-blown trees, drifting particles, moving shadows, or rippling water. Use cinematic camera motion — parallax, panning, zooming — to explore the space with depth and realism.

Prioritize high-resolution textures, natural lighting, soft shadows, and atmospheric depth. Avoid collage-like or flat visuals.

The result should feel like a rich, dynamic moment from a nature film — immersive, emotional, and alive.
TEXT;

        return $this->videoGenerator->generate($imageUrl, $idea);
    }
}
