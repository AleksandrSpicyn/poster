<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Classes\Generators\ImageGenerator\ImageGenerator;
use App\Data\ImageReferenceData;
use App\Enums\TransitionMode;
use App\Models\CharacterRevision;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\Series;
use App\Repositories\MediaRepository;
use App\Repositories\PromptRepository;
use App\Support\Integrations\Payload;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class RenderShotImage
{
    public function __construct(
        private ImageGenerator $images,
        private MediaRepository $media,
        private PromptRepository $prompts,
    ) {}

    public function run(GenerationOperation $operation): Media
    {
        $prompt = $this->prompts->forOperation($operation);
        $existing = $prompt->getFirstMedia(Prompt::SHOT_IMAGE);

        if ($existing instanceof Media) {
            return $existing;
        }

        $snapshot = $operation->request_snapshot;
        $sourceId = $snapshot['source_media_id'] ?? null;
        $source = is_int($sourceId) ? $this->media->findOrFail($sourceId) : null;
        $workFile = null;

        try {
            if ($source instanceof Media) {
                if (hash_file('sha256', $source->getPath()) !== ($snapshot['source_sha256'] ?? null)) {
                    throw new RuntimeException('The pinned continuation source changed.');
                }

                $workFile = Storage::disk('local')->path('shot-work/' . Str::uuid() . '.png');
                File::ensureDirectoryExists(dirname($workFile), 0700);
                $time = $snapshot['source_time_ms'] ?? null;
                $seek = is_int($time) && $time > 0 ? ['-ss', (string) ($time / 1000)] : ['-sseof', '-1'];
                $frames = is_int($time) && $time > 0 ? ['-frames:v', '1'] : [];
                $input = [
                    'ffmpeg',
                    '-nostdin',
                    '-v',
                    'error',
                    '-protocol_whitelist',
                    'file,pipe',
                    '-f',
                    'mov',
                ];
                $output = [
                    '-i',
                    $source->getPath(),
                    '-map',
                    '0:v:0',
                    '-fps_mode',
                    'passthrough',
                    ...$frames,
                    '-update',
                    '1',
                    '-y',
                    $workFile,
                ];
                Process::timeout(60)->run([
                    ...$input,
                    ...$seek,
                    ...$output,
                ])->throw();

                // An audio tail can extend beyond the final second of video.
                if ($frames === [] && ! File::isFile($workFile)) {
                    Process::timeout(60)->run([...$input, ...$output])->throw();
                }

                if (! File::isFile($workFile) || @getimagesize($workFile) === false) {
                    throw new RuntimeException('The selected continuation point has no readable frame.');
                }
            }

            if (($snapshot['transition_mode'] ?? null) === TransitionMode::CONTINUE_SCENE->value) {
                if ($workFile === null) {
                    throw new RuntimeException('The continued shot has no pinned opening frame.');
                }

                return $prompt->addMedia($workFile)->toMediaCollection(Prompt::SHOT_IMAGE, 'local');
            }

            $preparedReferences = [];
            $characters = $snapshot['characters'] ?? null;

            if (! is_array($characters)) {
                throw new RuntimeException('The pinned character snapshot is invalid.');
            }

            $ids = collect($characters)->pluck('references')->filter(static fn(mixed $references): bool => is_array($references))
                ->collapse()->pluck('media_id')->filter(static fn(mixed $id): bool => is_int($id))->values()->all();
            $pinned = $this->media->byIds($ids);

            foreach ($characters as $character) {
                if (! is_array($character) || ! is_int($character['id'] ?? null) || ! is_int($character['character_id'] ?? null) || ! is_array($character['references'] ?? null)) {
                    throw new RuntimeException('The pinned character references are invalid.');
                }

                foreach ($character['references'] as $index => $reference) {
                    if (! is_array($reference) || ! is_int($reference['media_id'] ?? null)) {
                        throw new RuntimeException('The pinned media reference is invalid.');
                    }

                    $media = $pinned->get($reference['media_id']);

                    if ($media === null || $media->model_type !== new CharacterRevision()->getMorphClass()
                        || (int) $media->model_id !== $character['id'] || $media->collection_name !== CharacterRevision::REFERENCES) {
                        throw new RuntimeException('The pinned media reference is invalid.');
                    }

                    if (hash_file('sha256', $media->getPath()) !== ($reference['sha256'] ?? null)) {
                        throw new RuntimeException('An approved reference changed after this take was requested.');
                    }

                    $data = new ImageReferenceData($media->getPath(), 'Character ' . $character['character_id'] . ', version ' . $character['id'] . ', view ' . ($index + 1));

                    $preparedReferences[$media->id] = $data;
                }
            }

            $assets = $snapshot['series_assets'] ?? [];

            if (! is_array($assets)) {
                throw new RuntimeException('Invalid series reference snapshot.');
            }

            $assetMedia = $this->media->byIds(array_map(intval(...), array_keys($assets)));

            foreach ($assets as $id => $asset) {
                if (! is_array($asset) || ($asset['kind'] ?? null) === 'sound') {
                    continue;
                }

                $item = $assetMedia->get((int) $id);

                if ($item === null || $item->disk !== 'local' || $item->model_type !== new Series()->getMorphClass() || (int) $item->model_id !== ($snapshot['series_id'] ?? null)
                    || $item->collection_name !== Series::ASSETS || hash_file('sha256', $item->getPath()) !== ($asset['checksum'] ?? null)) {
                    throw new RuntimeException('The approved series reference is unavailable or changed.');
                }

                $preparedReferences[$item->id] = new ImageReferenceData($item->getPath(), $item->name);
            }

            $referenceIds = $snapshot['reference_media_ids'] ?? null;

            if (! is_array($referenceIds)) {
                throw new RuntimeException('The selected reference order is missing.');
            }

            $references = [];

            foreach ($referenceIds as $referenceId) {
                if (! is_int($referenceId) || ! isset($preparedReferences[$referenceId])) {
                    throw new RuntimeException('The selected reference is not part of the pinned character versions.');
                }

                $references[] = $preparedReferences[$referenceId];
            }

            if ($workFile !== null) {
                $position = $snapshot['source_reference_position'] ?? null;

                if (! is_int($position) || count($references) >= 10) {
                    throw new RuntimeException('The continuity reference position is invalid.');
                }

                array_splice($references, $position, 0, [new ImageReferenceData($workFile, 'Previous scene state; preserve clothing, props and environment while changing camera.')]);
            }

            $url = $this->images->generate(Payload::string($snapshot['image_prompt'] ?? null, 'image_prompt'), $references);
            $disk = Storage::disk('public');
            $prefix = rtrim($disk->url('generated-images'), '/') . '/';

            if (! str_starts_with($url, $prefix) || preg_match('/\A[a-zA-Z0-9_-]+\.png\z/', substr($url, strlen($prefix))) !== 1) {
                throw new RuntimeException('The image adapter did not return an owned generated image.');
            }

            return $prompt->addMedia($disk->path('generated-images/' . substr($url, strlen($prefix))))->toMediaCollection(Prompt::SHOT_IMAGE, 'local');
        } finally {
            if ($workFile !== null && File::isFile($workFile)) {
                File::delete($workFile);
            }
        }
    }
}
