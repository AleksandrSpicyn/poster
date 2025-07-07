<?php

namespace App\Jobs;

use App\Classes\Generators\VideoGenerator\AlternativeVideoGenerator;
use App\Classes\Generators\VideoGenerator\VideoGenerator;
use App\Events\VideoGenerated;
use App\Models\Prompt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CheckVideoCreation implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public function __construct(
        private readonly Prompt $prompt,
        private readonly bool $alternative = false,
    ) {
    }

    public function handle(): void
    {
        try {
            $checkClass = $this->alternative
                ? AlternativeVideoGenerator::class
                : VideoGenerator::class;

            $result = app($checkClass)->check($this->prompt->video_id);

            if (!$result) {
                $this->release(60);

                return;
            }

            $this->prompt->update(['video_url' => $result]);

            event(new VideoGenerated($this->prompt));
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }
}
