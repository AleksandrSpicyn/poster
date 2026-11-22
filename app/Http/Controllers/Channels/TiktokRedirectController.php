<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Enums\ChannelType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TiktokRedirectController
{
    public function __invoke(Request $request): RedirectResponse
    {
        return to_route('channels.provider-callback', [
            'provider' => ChannelType::TIKTOK->value,
            ...$request->only(['state', 'code', 'error', 'error_description', 'scopes']),
        ])->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
