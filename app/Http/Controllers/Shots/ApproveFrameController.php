<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shots;

use App\Actions\Shots\ApproveOpeningFrameAction;
use App\Data\Shots\ApproveOpeningFrameData;
use App\Http\Controllers\EpisodeController;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\Scene;
use App\Models\Series;
use App\Models\Shot;
use Illuminate\Http\RedirectResponse;

class ApproveFrameController extends EpisodeController
{
    /**
     * Согласование первого кадра дубля
     *
     * @group Кабинет
     * @subgroup Эпизоды
     * @authenticated
     * @response 302
     * @response 422
     */
    public function __invoke(Series $series, Episode $episode, EpisodeRevision $revision, Scene $scene, Shot $shot, Prompt $prompt, GenerationOperation $operation, ApproveOpeningFrameData $data, ApproveOpeningFrameAction $action): RedirectResponse
    {
        $action->run($series, $prompt, $operation->id, $data->asset_id);

        return $this->redirectToEpisode($series, $episode)->withFragment('shots');
    }
}
