<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Classes\Generators\AudioGenerator\AudioGenerator;
use App\Enums\VideoAudioSource;
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

    public function run(string $title, VideoAudioSource $source = VideoAudioSource::SEPARATE): ?string
    {
        if ($source === VideoAudioSource::EMBEDDED) {
            return null;
        }

        $prompt = <<<TEXT
Create instrumental background music for this action-driven visual story: "$title".
Start on the first beat with a catchy, propulsive rhythmic hook. Use a cohesive groove of percussion, bass, plucked strings, and short brass or keyboard accents, clearly audible on phone speakers.
Shape the opening {$this->generalSettings->duration} seconds as a complete musical arc: an immediate hook, rising rhythmic tension through the action, a sharp accent for the final reversal, and a short resolved musical button. Keep playful comic urgency and a satisfying payoff; avoid a slow ambient introduction or a static loop.
Music is the main audio bed. No vocals, spoken words, voiceover, narration, dialogue, lyrics, singing, or imitation of an existing song or artist.
Target duration: {$this->generalSettings->duration} seconds.
TEXT;

        return $this->audioGenerator->generate($title, $prompt);
    }
}
