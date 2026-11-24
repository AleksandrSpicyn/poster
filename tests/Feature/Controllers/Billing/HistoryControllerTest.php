<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Tests\Support\billingOrder;
use function Tests\Support\billingOwner;

test('история открывается по прямой ссылке с фильтром и проверяет вкладку', function (): void {
    $owner = billingOwner();
    $id = billingOrder($owner);

    actingAs($owner)->get(route('billing.history'))->assertInertia(fn(Assert $page): Assert => $page->where('history.data.0.id', $id));
    actingAs($owner)->get(route('billing.history', ['tab' => 'seconds', 'project' => 42]))
        ->assertInertia(fn(Assert $page): Assert => $page->where('section', 'seconds')->where('projectFilter', 42)->has('history.data', 0));
    actingAs($owner)->getJson(route('billing.history', ['tab' => 'plans']))->assertUnprocessable();
});
