<?php

declare(strict_types=1);

namespace App\Http\Controllers\Posts;

use App\Actions\Publications\GetTiktokPublicationFormAction;
use App\Data\PageData;
use App\Http\Resources\TiktokVideoResource;
use App\Models\Channel;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TiktokCreateController
{
    public function __invoke(Channel $channel, PageData $page, GetTiktokPublicationFormAction $action): Response
    {
        $form = $action->run($channel, $page->page);

        return inertia('Posts/Tiktok', ['channelId' => $channel->id, ...$form, 'videos' => $form['videos']->through(
            fn(Media $media): array => TiktokVideoResource::make($media)->resolve(),
        )]);
    }
}
