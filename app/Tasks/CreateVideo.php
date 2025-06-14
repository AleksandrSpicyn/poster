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

Create a vivid, lifelike animated scene where the animal is fully integrated into its environment. The animal should move naturally and responsively — turning its head, blinking, shifting weight, sniffing, reacting to sounds or light changes — as if truly aware of its surroundings. Avoid robotic or repetitive motion; prioritize subtle, organic gestures that convey curiosity and presence.

The environment must feel alive too: animate ambient elements like wind rustling grass or trees, water movement, drifting particles, or dynamic lighting shifts. The scene should have depth and realism, using cinematic camera techniques like smooth parallax motion, slow zooms, subtle panning or orbiting shots to explore the space and create immersion.

Prioritize high-resolution textures, realistic lighting, soft shadows, and atmospheric depth. Avoid flat or collage-like compositions — the goal is to evoke a single living moment in time, not a static frame.

The final animation should feel like a short, compelling shot from a wildlife film — emotionally engaging, visually rich, and fully alive.
TEXT;

        return $this->videoGenerator->generate($imageUrl, $prompt);
    }
}
