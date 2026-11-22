<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('предлагает зарегистрированные драйверы', function (): void {
    actingAs(User::factory()->create());

    get(route('clipping.create'))
        ->assertInertia(fn(Assert $page): Assert => $page->component('Clipping/Create')->where('drivers.opusclip', 'OpusClip'));
});
