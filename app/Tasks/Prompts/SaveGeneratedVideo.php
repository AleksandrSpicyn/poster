<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Classes\Billing\MeteredVideo;
use App\Enums\ReservationStatus;
use App\Models\Prompt;
use App\Repositories\GenerationReservationRepository;
use App\Repositories\PromptRepository;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Прикрепляет собранный файл к Prompt через Media Library, затем отмечает
 * сборку завершённой; generated описывает сборку, а не публикацию во всех каналах.
 */
class SaveGeneratedVideo
{
    public function __construct(private readonly PromptRepository $prompts, private readonly GenerationReservationRepository $usage) {}

    /**
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function run(Prompt $prompt, string $path): void
    {
        $existing = $prompt->getFirstMedia();
        MeteredVideo::assertMatchesQuote($existing?->getPath() ?? $path, $this->usage->quotedSeconds($prompt->id), data_get($prompt->input_snapshot, 'video_request.resolution') === '480p');

        if (! $existing instanceof Media) {
            $prompt->addMedia($path)->toMediaCollection();
        }

        DB::transaction(function () use ($prompt): void {
            $this->prompts->findLockedOrFail($prompt->id);
            $this->usage->assertDeliverable($prompt->id);
            $this->prompts->update($prompt, ['generated' => true]);
            $this->usage->setStatus([$prompt->id], ReservationStatus::CONSUMED);
        }, 3);
        $this->prompts->reloadMedia($prompt);
    }
}
