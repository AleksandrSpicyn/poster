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

Create a cinematic, photorealistic video showing what would happen if a wild animal suddenly found itself in a completely foreign country or ecosystem. The animal should move naturally — blinking, shifting its weight, sniffing the air, looking around with curiosity or confusion. Its movements must feel organic, unscripted, and full of life — no looping or robotic motions.

The environment should be rich and alive: wind rustling through unfamiliar trees, particles floating in the air, dynamic lighting with soft shadows moving over the terrain. Include water reflections, natural sounds, or sudden distant noises that trigger subtle animal reactions. Use smooth camera movement (panning, slow zooms, parallax effects) to explore the scene and add immersive depth.

Textures must be high-resolution with detailed fur, feathers, or skin, and lighting should simulate real-world physics — soft, directional, and changing over time. Prioritize realism, atmosphere, and emotional resonance — as if this was a dramatic moment from a high-end nature documentary.
TEXT;

        return $this->videoGenerator->generate($imageUrl, $prompt);
    }
}
