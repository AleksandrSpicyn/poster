<?php

declare(strict_types=1);

use App\Enums\BillingProduct;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('публичные тарифы показывают серверный каталог', function (bool $authenticated): void {
    if ($authenticated) {
        actingAs(User::factory()->create());
    }

    $catalog = collect(BillingProduct::cases())->map(fn(BillingProduct $product): array => [
        'code' => $product->value,
        'label' => $product->label(),
        'seconds' => $product->seconds(),
        'amount' => $product->amountKopecks(),
        'monthly' => $product->isMonthly(),
    ])->all();

    get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Pricing')
            ->where('catalog', $catalog)
            ->missing('balance')
            ->missing('account')
            ->missing('history')
            ->missing('checkout'));
})->with(['гость' => false, 'владелец' => true]);
