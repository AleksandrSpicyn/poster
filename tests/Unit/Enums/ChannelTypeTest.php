<?php

declare(strict_types=1);

use App\Enums\ChannelType;

test('типы каналов сохраняют публичные значения и подписи', function (): void {
    expect(ChannelType::values())
        ->toBe(['youtube', 'tiktok', 'instagram', 'vk'])
        ->and(ChannelType::labels())
        ->toBe([
            'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'instagram' => 'Instagram', 'vk' => 'VK',
        ])
        ->and(ChannelType::has('youtube'))
        ->toBeTrue()
        ->and(ChannelType::has('unknown'))
        ->toBeFalse();
});
