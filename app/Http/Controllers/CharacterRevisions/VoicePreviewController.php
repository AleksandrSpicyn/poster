<?php

declare(strict_types=1);

namespace App\Http\Controllers\CharacterRevisions;

use App\Actions\Characters\GenerateVoicePreviewAction;
use App\Data\Characters\VoicePreviewData;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Http\Response;

class VoicePreviewController
{
    /** Проба голоса для текущей утверждённой версии или последнего черновика персонажа. */
    public function __invoke(Project $project, Series $series, Character $character, CharacterRevision $revision, VoicePreviewData $data, GenerateVoicePreviewAction $action): Response
    {
        $sample = $action->run($series, $character, $revision, $data);

        return response($sample->wav, 200, ['Content-Type' => 'audio/wav', 'Cache-Control' => 'private, no-store']);
    }
}
