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
This video is based on the following concept: "$idea".
Animate the image with smooth cinematic motion. Add dynamic lighting, parallax depth, and surreal energy.
Use immersive camera motion (slow zoom, pan, or parallax) to bring the idea to life.
Do not animate characters unless they are obviously in motion.
TEXT;

        return $this->videoGenerator->generate($imageUrl, $prompt);
    }
}
