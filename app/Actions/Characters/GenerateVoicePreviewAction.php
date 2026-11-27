<?php

declare(strict_types=1);

namespace App\Actions\Characters;

use App\Classes\Generators\AudioGenerator\BytePlusSpeech;
use App\Classes\Generators\VoiceSample;
use App\Data\Characters\VoicePreviewData;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\Series;
use App\Repositories\CharacterRevisionRepository;
use Illuminate\Validation\ValidationException;

readonly class GenerateVoicePreviewAction
{
    public function __construct(private BytePlusSpeech $speech, private CharacterRevisionRepository $revisions) {}

    public function run(Series $series, Character $character, CharacterRevision $revision, VoicePreviewData $data): VoiceSample
    {
        if ($series->archived_at !== null || $character->archived_at !== null || $revision->lock_version !== $data->lock_version || ! $this->revisions->acceptsVoiceSample($revision, $character)) {
            throw ValidationException::withMessages(['revision' => 'Обновите текущую утверждённую версию или последний черновик персонажа для создания пробы.']);
        }

        return $this->speech->generate($data);
    }
}
