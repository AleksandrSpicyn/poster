<?php

namespace App\Tasks;

use App\Classes\Generators\AudioGenerator\AudioGenerator;
use App\Settings\GeneralSettings;

class CreateAudio extends Task
{
    public function __construct(
        private readonly AudioGenerator $audioGenerator,
        private readonly GeneralSettings $generalSettings,
    ) {
    }

    public function run(string $title): string
    {
        $prompt = <<<TEXT
Generate a epic cinematic instrumental soundtrack with rich atmosphere and emotional build-up.
Use orchestral instruments, cinematic textures, ambient synths, and deep percussion.
The music should feel powerful, immersive, and emotional.
No vocals. Duration: {$this->generalSettings->duration} seconds.
TEXT;

        return $this->audioGenerator->generate($title, $prompt);
    }
}
