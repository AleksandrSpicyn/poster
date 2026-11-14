<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

test('статические публичные страницы возвращают нужный компонент', function (): void {
    get(route('policy'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->component('Policy'));
});
