<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Models\Prompt;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

/**
 * Прикрепляет собранный файл к Prompt через Media Library, затем отмечает
 * сборку завершённой; generated описывает сборку, а не публикацию во всех каналах.
 */
class SaveGeneratedVideo
{
    /**
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function run(Prompt $prompt, string $path): void
    {
        $prompt->addMedia($path)->toMediaCollection();
        $prompt->update(['generated' => true]);
    }
}
