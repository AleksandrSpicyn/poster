<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Tests\Support\billingOrder;
use function Tests\Support\billingOwner;

test('заказ доступен только владельцу по сохранённой цене', function (): void {
    $owner = billingOwner();
    $order = billingOrder($owner);
    $url = route('billing.orders.show', $order);

    actingAs(billingOwner())->get($url)->assertNotFound();
    actingAs($owner)->get($url)->assertInertia(fn(Assert $page): Assert => $page->where('order.amount_kopecks', 99000)
        ->where('order.id', $order)->where('confirmationUrl', null)->missing('order.request_key'));
});
