<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Appearance;
use Illuminate\Container\Attributes\Config;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

/**
 * Формирует общие props кабинета и публичных Inertia-страниц: auth, Ziggy,
 * значения темы и состояние sidebar; данные конкретного списка задаёт контроллер.
 */
class HandleInertiaRequests extends Middleware
{
    public function __construct(#[Config('app.name')] private readonly string $appName) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var string $quote Inspiring stores quote strings. */
        $quote = Inspiring::quotes()->random();
        $message = str($quote)->beforeLast(' - ')->toString();
        $author = str($quote)->afterLast(' - ')->toString();

        return [
            ...parent::share($request),
            'name' => $this->appName,
            'appearanceValues' => array_column(Appearance::cases(), 'value', 'name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'can_view_logs' => $request->user() !== null && Gate::forUser($request->user())->allows('viewLogViewer'),
                'can_view_queues' => $request->user() !== null && Gate::forUser($request->user())->allows('viewHorizon'),
                'can_manage_channels' => $request->user() !== null && Gate::forUser($request->user())->allows('manageChannels'),
                'can_manage_series' => $request->user() !== null && Gate::forUser($request->user())->allows('manageSeries'),
            ],
            'ziggy' => [
                ...new Ziggy()->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'realtime' => [
                'enabled' => config('broadcasting.default') === 'reverb' && filled(config('broadcasting.connections.reverb.key')),
                'key' => config('broadcasting.connections.reverb.key'),
            ],
            'contentScheduleEnabled' => (bool) config('production.schedule_enabled'),
        ];
    }
}
