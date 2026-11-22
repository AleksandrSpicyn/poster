<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('открывает отчёт подтверждённому пользователю и проверяет период', function (): void {
    get(route('dashboard.analytics'))->assertRedirect(route('login'));
    actingAs(User::factory()->unverified()->create());
    get(route('dashboard.analytics'))->assertRedirect(route('verification.notice'));

    actingAs(User::factory()->create());

    get(route('dashboard.analytics', ['days' => 30, 'platform' => 'instagram']))
        ->assertInertia(fn(Assert $page): Assert => $page->component('PublicationAnalytics')->where('analytics.filters.days', 30)
            ->where('analytics.filters.platform', 'instagram')->has('analytics.daily', 30)->has('analytics.publications', 0));
    getJson(route('dashboard.analytics', ['days' => 999]))->assertUnprocessable()->assertJsonValidationErrors('days');
});
