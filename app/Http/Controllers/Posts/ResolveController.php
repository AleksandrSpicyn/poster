<?php

declare(strict_types=1);

namespace App\Http\Controllers\Posts;

use App\Actions\Episodes\ResolveEpisodePublicationAction;
use App\Data\Episodes\ResolveEpisodePublicationData;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class ResolveController
{
    public function __invoke(PublicationRequest $publication, ResolveEpisodePublicationData $data, #[CurrentUser] User $owner, ResolveEpisodePublicationAction $action): RedirectResponse
    {
        $action->run($publication, $owner, $data->external_id);

        return to_route('posts');
    }
}
