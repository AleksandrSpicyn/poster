<?php

declare(strict_types=1);

use App\Enums\BillingProduct;

test('версия пилотного каталога задаёт цену и длительность', function (): void {
    $offers = array_map(static fn(BillingProduct $product): array => [$product->label(), $product->amountKopecks(), $product->seconds(), $product->isMonthly()], BillingProduct::cases());

    expect(BillingProduct::CATALOG_VERSION)->toBe('2026-11-23');
    expect($offers)->toBe([
        ['Разовый пакет', 29_000, 30, false],
        ['Автор', 99_000, 120, true],
        ['Студия', 279_000, 360, true],
    ]);
});
