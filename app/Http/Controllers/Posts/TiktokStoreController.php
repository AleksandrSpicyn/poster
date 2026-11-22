<?php

declare(strict_types=1);

namespace App\Http\Controllers\Posts;

use App\Actions\Publications\RequestTiktokPublicationAction;
use App\Data\Tiktok\TiktokPublicationData;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class TiktokStoreController
{
    public function __invoke(Channel $channel, #[CurrentUser] User $owner, TiktokPublicationData $data, RequestTiktokPublicationAction $action): RedirectResponse
    {
        $action->run($channel, $owner, $data);

        return to_route('posts');
    }
}
