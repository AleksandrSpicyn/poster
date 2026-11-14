<?php

declare(strict_types=1);

use App\Enums\ChannelType;

test('типы каналов сохраняют публичные значения и подписи', function (): void {
    expect(ChannelType::values())
        ->toBe(['youtube', 'tiktok', 'instagram', 'vk'])
        ->and(ChannelType::labels())
        ->toBe([
            'youtube' => 'Youtube', 'tiktok' => 'Tiktok', 'instagram' => 'Instagram', 'vk' => 'Vk',
        ])
        ->and(ChannelType::has('youtube'))
        ->toBeTrue()
        ->and(ChannelType::has('unknown'))
        ->toBeFalse();
});
