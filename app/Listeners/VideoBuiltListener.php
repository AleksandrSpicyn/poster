<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Classes\Publishers\PublisherFactory;
use App\Enums\ChannelType;
use App\Events\VideoBuilt;
use App\Models\Channel;
use App\Tasks\Projects\GetPromptProject;
use Illuminate\Support\Facades\Log;

/**
 * После сборки отправляет ролик в каждый канал проекта через PublisherFactory.
 * Обработка синхронная, но отдельный адаптер может продолжить публикацию в очереди.
 */
readonly class VideoBuiltListener
{
    public function __construct(private PublisherFactory $publishers, private GetPromptProject $getProject) {}

    public function handle(VideoBuilt $event): void
    {
        $prompt = $event->getPrompt();
        $project = $this->getProject->run($prompt);

        if ($prompt->shot_id !== null || $project?->is_active !== true) {
            return;
        }

        Log::info("VideoBuilt: {$event->getPrompt()->getFirstMediaUrl()}");

        $project->channels->filter(fn(Channel $channel): bool => $channel->type !== ChannelType::TIKTOK && $channel->owner_id === $project->owner_id && $channel->is_active && filled($channel->credentials['access_token'] ?? null))->each(function (Channel $channel) use ($prompt): void {
            $this->publishers->make($channel, $prompt)->publish();
        });
    }
}
