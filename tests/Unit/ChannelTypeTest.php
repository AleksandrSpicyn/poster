<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Tasks\CreateImagePrompt;

test('типы каналов сохраняют публичные значения и подписи', function (): void {
    expect(ChannelType::values())->toBe(['youtube', 'tiktok', 'instagram', 'vk'])
        ->and(ChannelType::labels())->toBe([
            'youtube' => 'Youtube', 'tiktok' => 'Tiktok', 'instagram' => 'Instagram', 'vk' => 'Vk',
        ])
        ->and(ChannelType::has('youtube'))->toBeTrue()
        ->and(ChannelType::has('unknown'))->toBeFalse();
});

test('промпт изображения сохраняет идею и вертикальный формат', function (): void {
    expect(CreateImagePrompt::run('Fox in Tokyo'))
        ->toStartWith('Idea: Fox in Tokyo, Styles: photo-realistic')
        ->toContain('vertical composition', '9:16 aspect ratio');
});
