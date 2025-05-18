<?php

namespace App\Actions;

use App\Events\VideoBuiled;
use App\Models\Prompt;
use App\Tasks\BuildVideo;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class SaveVideoAction extends Action
{
    public function __construct(private readonly BuildVideo $buildVideo)
    {
    }

    /**
     * @throws FileIsTooBig
     * @throws FileDoesNotExist
     */
    public function run(Prompt $prompt): void
    {
        if (!$prompt->video_url || !$prompt->audio_url) {
            return;
        }

        $path = $this->buildVideo->run($prompt);

        $prompt->addMedia($path)->toMediaCollection();

        $prompt->update(['generated' => true]);

        event(new VideoBuiled($prompt));
    }
}
