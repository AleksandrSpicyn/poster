<?php

declare(strict_types=1);

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
    ) {}

    public function handle(VideoGenerator $videoGenerator, AlternativeVideoGenerator $alternativeVideoGenerator): void
    {
        $this->prompt->refresh();

        if ($this->prompt->video_url || ! $this->prompt->video_id) {
            return;
        }

        try {
            $generator = $this->alternative ? $alternativeVideoGenerator : $videoGenerator;
            $result = $generator->check($this->prompt->video_id);

            if (! $result) {
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
