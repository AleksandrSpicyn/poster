<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Tests\Support\singleVideoFixture;

test('параметры доступны владельцу до создания первой сборки', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $route = route('series_assemblies.create', $context['series']);

    actingAs($context['user'])
        ->get($route)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('SeriesAssemblies/Create')
            ->where('parameters.can_build', true)
            ->has('parameters.parts', 3));
    actingAs(User::factory()->create())
        ->get($route)
        ->assertNotFound();
});
