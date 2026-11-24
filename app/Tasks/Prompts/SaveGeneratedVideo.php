<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Classes\Billing\MeteredVideo;
use App\Models\Prompt;
use App\Repositories\GenerationReservationRepository;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class SaveGeneratedVideo
{
    public function __construct(private GenerationReservationRepository $usage) {}

    /**
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function run(Prompt $prompt, string $path): void
    {
        $this->verify($prompt, $path);
        $this->usage->assertDeliverable($prompt->id);
        $prompt->addMedia($path)->toMediaCollection();
    }

    public function verifyAttachment(Prompt $prompt, Media $existing): void
    {
        $this->verify($prompt, $existing->getPath(), $existing->size);
    }

    private function verify(Prompt $prompt, string $path, ?int $expectedSize = null): void
    {
        $size = is_file($path) ? filesize($path) : false;

        if ($size === false || $size === 0 || ($expectedSize !== null && $expectedSize !== $size)) {
            throw new RuntimeException('Файл собранного ролика отсутствует или записан не полностью.');
        }

        MeteredVideo::assertMatchesQuote($path, $this->usage->quotedSeconds($prompt->id), data_get($prompt->input_snapshot, 'video_request.resolution') === '480p');
    }
}
