<?php

declare(strict_types=1);

namespace App\Http\Controllers\Posts;

use App\Actions\Publications\GetTiktokPreviewAction;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TiktokPreviewController extends Controller
{
    public function __invoke(Channel $channel, Media $media, GetTiktokPreviewAction $action): BinaryFileResponse
    {
        return $this->privateFile($action->run($channel, $media));
    }
}
