<?php

namespace App\Jobs;

use App\Classes\Generators\VideoGenerator\VideoGenerator;
use App\Events\VideoGenerated;
use App\Models\Prompt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CheckVideoCreation implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(private readonly Prompt $prompt)
    {
    }

    public function handle(VideoGenerator $videoGenerator): void
    {
        try {
            $result = $videoGenerator->check($this->prompt->video_id);

            if (!$result) {
                $this->release(10);
            }

            $this->prompt->update(['video_url' => $result]);

            event(new VideoGenerated($this->prompt));
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }
}
