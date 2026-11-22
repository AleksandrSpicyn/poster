<?php

declare(strict_types=1);

use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('список сериалов ограничен страницей и показывает счётчики', function (): void {
    Series::factory()->recycle(workspaceOwner())->count(21)->create();

    actingAs(serialOperator())
        ->get(route('series.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Series/Index')
            ->has('series.data', 20)
            ->where('series.meta.total', 21)
            ->where('series.data.0.characters_count', 0));
});

test('список проверяет номер страницы', function (): void {
    actingAs(serialOperator())
        ->getJson(route('series.index', ['page' => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('page');
});

test('гость не видит библиотеку сериалов', function (): void {
    get(route('series.index'))->assertRedirect(route('login'));
});

test('неподтверждённый пользователь не видит сериалы', function (): void {
    actingAs(User::factory()->unverified()->create())
        ->get(route('series.index'))
        ->assertRedirect(route('verification.notice'));
});

test('подтверждённый посторонний пользователь не читает сериалы', function (): void {
    config(['services.admin.email' => 'operator@example.test', 'horizon.allowed_emails' => []]);

    actingAs(User::factory()->create(['email' => 'reader@example.test']))
        ->get(route('series.index'))
        ->assertInertia(fn(Assert $page): Assert => $page->where('series.meta.total', 0));
});

test('число запросов списка не растёт с количеством сериалов', function (): void {
    $owner = workspaceOwner();
    actingAs(serialOperator());
    Series::factory()->recycle($owner)->create();

    $countQueries = static function (int $total): int {
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            get(route('series.index'))
                ->assertOk()
                ->assertInertia(fn(Assert $page): Assert => $page->has('series.data', $total));

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    };

    $single = $countQueries(1);
    Series::factory()->recycle($owner)->count(9)->create();

    expect($countQueries(10))->toBeLessThanOrEqual($single);
});

test('фильтры каталога сохраняют владение и отделяют активные сериалы', function (): void {
    $owner = User::factory()->create();
    $active = Project::factory()->create(['owner_id' => $owner->id, 'is_active' => true]);
    $paused = Project::factory()->create(['owner_id' => $owner->id, 'is_active' => false]);
    $recent = Series::factory()->for($active)->create(['updated_at' => now()]);
    $older = Series::factory()->for($active)->create(['updated_at' => now()->subDay()]);
    Series::factory()->for($active)->create(['archived_at' => now()]);
    Series::factory()->for($paused)->create();
    Series::factory()->create();
    $episode = Episode::factory()->for($recent)->create();
    $revision = EpisodeRevision::factory()->for($episode)->create();
    $assembly = EpisodeAssembly::factory()->for($revision, 'episodeRevision')->create();
    $episode->update(['accepted_assembly_id' => $assembly->id]);

    actingAs($owner)
        ->get(route('series.index', ['active' => 1, 'updated' => 1]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('filters.active', true)
            ->where('filters.updated', true)
            ->where('series.meta.total', 2)
            ->where('series.data.0.id', $recent->id)
            ->where('series.data.0.accepted_episodes_count', 1)
            ->where('series.data.1.id', $older->id));

    get(route('series.index'))
        ->assertInertia(fn(Assert $page): Assert => $page->where('series.meta.total', 4));
});
