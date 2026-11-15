<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Appearance;
use Illuminate\Container\Attributes\Config;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
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
            ],
            'ziggy' => [
                ...new Ziggy()->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
