<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Classes\Generators\AudioGenerator\AudioGenerator;
use App\Settings\GeneralSettings;

/**
 * Формирует запрос инструментальной музыки с длительностью из общих настроек.
 * Возвращает идентификатор задачи; ссылка на трек придёт через callback Suno.
 */
readonly class CreateAudio
{
    public function __construct(
        private AudioGenerator $audioGenerator,
        private GeneralSettings $generalSettings,
    ) {}

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
