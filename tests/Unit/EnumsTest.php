<?php

declare(strict_types=1);

use App\Traits\EnumTrait;

test('все enum приложения используют EnumTrait', function (): void {
    expect('App')
        ->enums()
        ->toUseTrait(EnumTrait::class);
});
