<?php

declare(strict_types=1);

namespace App\Classes\Generators\ImageGenerator\Codex;

use App\Classes\Generators\ImageGenerator\ImageGenerator;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use RuntimeException;

readonly class Codex implements ImageGenerator
{
    public function generate(string $prompt): string
    {
        $path = Image::of($prompt)
            ->size('9:16')
            ->generate(provider: 'codex')
            ->storePublicly('generated-images', 'public');

        if (! is_string($path)) {
            throw new RuntimeException('Could not store the generated image.');
        }

        return Storage::disk('public')->url($path);
    }
}
