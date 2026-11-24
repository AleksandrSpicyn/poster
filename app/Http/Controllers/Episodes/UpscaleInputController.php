<?php

declare(strict_types=1);

namespace App\Http\Controllers\Episodes;

use App\Actions\Upscale\GetUpscaleInputAction;
use App\Models\VideoUpscaleOperation;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UpscaleInputController
{
    public function __invoke(VideoUpscaleOperation $operation, string $checksum, GetUpscaleInputAction $action): BinaryFileResponse
    {
        return response()->file($action->run($operation, $checksum), ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store']);
    }
}
