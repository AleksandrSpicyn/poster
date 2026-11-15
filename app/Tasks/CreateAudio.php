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
Instrumental underscore for this visual concept: "$title".
Match the scene's mood: playful curiosity and light surprise for comedy, gentle wonder for a scenic discovery. Use a small, cohesive palette of plucked instruments, warm keys, and light percussion rather than a dense trailer orchestra.
Start immediately with a clear, memorable rhythmic motif; make the opening {$this->generalSettings->duration} seconds usable as a complete short cue, with a small lift and a tidy musical resolution within that window. Keep the arrangement clear on phone speakers. No vocals, spoken words, long intro, or imitation of an existing song or artist.
Target duration: {$this->generalSettings->duration} seconds.
TEXT;

        return $this->audioGenerator->generate($title, $prompt);
    }
}
