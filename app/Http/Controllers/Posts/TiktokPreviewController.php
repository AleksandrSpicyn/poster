<?php

declare(strict_types=1);

namespace App\Http\Controllers\Posts;

use App\Actions\Publications\GetTiktokPublicationFormAction;
use App\Models\Channel;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TiktokPreviewController
{
    public function __invoke(Channel $channel, Media $media, GetTiktokPublicationFormAction $action): BinaryFileResponse
    {
        return response()->file($action->preview($channel, $media), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }
}
