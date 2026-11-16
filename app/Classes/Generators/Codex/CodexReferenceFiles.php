<?php

declare(strict_types=1);

namespace App\Classes\Generators\Codex;

use App\Data\ImageReferenceData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Files\LocalImage;
use Laravel\Ai\Files\StoredImage;
use RuntimeException;

readonly class CodexReferenceFiles
{
    private const int MAX_REFERENCE_BYTES = 20 * 1024 * 1024;

    private const int MAX_TOTAL_REFERENCE_BYTES = 50 * 1024 * 1024;

    private const int MAX_REFERENCE_DIMENSION = 6000;

    /**
     * @param array<Image> $references
     * @return list<ImageReferenceData>
     */
    public function prepare(array $references, string $workspace): array
    {
        $prepared = [];
        $totalBytes = 0;

        foreach (array_values($references) as $index => $reference) {
            if (! $reference instanceof LocalImage && ! $reference instanceof StoredImage) {
                throw new InvalidArgumentException('Codex accepts only local or stored reference images.');
            }

            if ($reference instanceof LocalImage) {
                $source = realpath($reference->path);

                if ($source === false || ! File::isFile($source) || ! is_readable($source)) {
                    throw new InvalidArgumentException('Codex reference image is not a readable local file.');
                }

                $size = File::size($source);
            } else {
                $size = Storage::disk($reference->disk)->size($reference->path);
                $source = null;
            }

            $totalBytes += $size;

            if ($size < 1 || $size > self::MAX_REFERENCE_BYTES || $totalBytes > self::MAX_TOTAL_REFERENCE_BYTES) {
                throw new InvalidArgumentException('Codex reference images exceed the file or total size limit.');
            }

            $stream = $source !== null ? fopen($source, 'rb') : Storage::disk($reference->disk)->readStream($reference->path);

            if ($stream === false || $stream === null) {
                throw new RuntimeException('Could not read the Codex reference image.');
            }

            try {
                $content = stream_get_contents($stream, self::MAX_REFERENCE_BYTES + 1);
            } finally {
                fclose($stream);
            }

            $image = @getimagesizefromstring($content);

            if (strlen($content) !== $size || $image === false
                || ! in_array($image[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)
                || $image[0] < 1 || $image[1] < 1
                || $image[0] > self::MAX_REFERENCE_DIMENSION || $image[1] > self::MAX_REFERENCE_DIMENSION) {
                throw new InvalidArgumentException('Codex references must be valid PNG, JPEG, or WebP images of at most 6000 pixels per dimension.');
            }

            $extension = match ($image[2]) {
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_JPEG => 'jpg',
                default => 'webp',
            };

            File::ensureDirectoryExists($workspace . '/references', 0700);
            $path = $workspace . '/references/reference-' . ($index + 1) . '.' . $extension;

            if (File::put($path, $content) === false) {
                throw new RuntimeException('Could not prepare a Codex reference image.');
            }

            $prepared[] = new ImageReferenceData($path, $reference->name() ?? 'Reference ' . ($index + 1));
        }

        return $prepared;
    }
}
