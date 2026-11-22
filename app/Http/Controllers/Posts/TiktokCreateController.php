<?php

declare(strict_types=1);

namespace App\Http\Controllers\Posts;

use App\Actions\Publications\GetTiktokPublicationFormAction;
use App\Data\PageData;
use App\Models\Channel;
use Inertia\Response;

class TiktokCreateController
{
    public function __invoke(Channel $channel, PageData $page, GetTiktokPublicationFormAction $action): Response
    {
        return inertia('Posts/Tiktok', ['channelId' => $channel->id, ...$action->run($channel, $page->page)]);
    }
}
