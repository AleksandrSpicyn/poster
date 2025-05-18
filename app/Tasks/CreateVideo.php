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

Bring the scene to life with expressive, realistic animation. The animal should actively interact with the environment — looking around, moving naturally, reacting to the surroundings. Avoid static posing or simple sliding — the character must feel present and alive in the scene.

Use cinematic camera motion such as parallax, panning, zooming, or rotation to explore the space. Enhance the atmosphere with natural lighting, realistic shadows, and environmental depth. Animate ambient elements like wind, reflections, or background movement to add immersion.

The result should feel like a vivid, living moment — not a slideshow or layered collage.
TEXT;

        return $this->videoGenerator->generate($imageUrl, $prompt);
    }
}
