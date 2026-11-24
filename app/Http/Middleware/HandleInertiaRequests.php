<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\Billing\GenerationBalanceData;
use App\Enums\Appearance;
use App\Repositories\BillingWorkspaceRepository;
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
    public function __construct(#[Config('app.name')] private readonly string $appName, private readonly BillingWorkspaceRepository $billing) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $cookie = $request->cookie('appearance');
        $appearance = is_string($cookie) ? Appearance::tryFrom($cookie) : null;

        return [
            ...parent::share($request),
            'name' => $this->appName,
            'appearance' => ($appearance ?? Appearance::SYSTEM)->value,
            'appearanceValues' => array_column(Appearance::cases(), 'value', 'name'),
            'quote' => $this->quote(...),
            ...($request->routeIs('pricing', 'contacts') ? [] : [
                'balance' => fn(): ?GenerationBalanceData => $user === null ? null : $this->billing->balance($user->id),
            ]),
            'auth' => [
                'user' => $user,
                'can_view_logs' => $user !== null && Gate::forUser($user)->allows('viewLogViewer'),
                'can_view_queues' => $user !== null && Gate::forUser($user)->allows('viewHorizon'),
                'can_manage_channels' => $user !== null && Gate::forUser($user)->allows('manageChannels'),
                'can_manage_series' => $user !== null && Gate::forUser($user)->allows('manageSeries'),
            ],
            'ziggy' => fn(): array => [
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

    /** @return array{message: string, author: string} */
    private function quote(): array
    {
        /** @var string $quote */
        $quote = Inspiring::quotes()->random();

        return [
            'message' => trim(str($quote)->beforeLast(' - ')->toString()),
            'author' => trim(str($quote)->afterLast(' - ')->toString()),
        ];
    }
}
