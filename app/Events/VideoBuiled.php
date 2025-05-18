<?php

namespace App\Events;

use App\Models\Prompt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoBuiled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(private readonly Prompt $prompt)
    {
    }

    public function getPrompt(): Prompt
    {
        return $this->prompt;
    }
}
