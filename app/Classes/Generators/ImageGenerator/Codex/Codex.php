<?php

declare(strict_types=1);

namespace App\Classes\Generators\ImageGenerator\Codex;

use App\Classes\Generators\ImageGenerator\ImageGenerator;
use App\Data\ImageReferenceData;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Image as ReferenceImage;
use Laravel\Ai\Image;
use RuntimeException;

readonly class Codex implements ImageGenerator
{
    /** @param list<ImageReferenceData> $references */
    public function generate(string $prompt, array $references = []): string
    {
        $response = Image::of($prompt)
            ->attachments(collect($references)->map(fn(ImageReferenceData $reference): ReferenceImage => ReferenceImage::fromPath($reference->path)->as($reference->label))->all())
            ->size('9:16')
            ->generate(provider: 'codex');
        $image = @getimagesizefromstring($response->firstImage()->content());

        if ($image === false || $image[2] !== IMAGETYPE_PNG || $image[0] < 720 || $image[1] < 1280 || $image[0] >= $image[1]) {
            throw new RuntimeException('Codex must return a portrait PNG of at least 720 by 1280 pixels.');
        }

        $path = $response->storePublicly('generated-images', 'public');

        if (! is_string($path)) {
            throw new RuntimeException('Could not store the generated image.');
        }

        return Storage::disk('public')->url($path);
    }
}
