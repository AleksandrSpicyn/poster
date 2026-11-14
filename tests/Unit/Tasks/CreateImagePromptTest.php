<?php

declare(strict_types=1);

use App\Tasks\CreateImagePrompt;

test('промпт изображения сохраняет идею и вертикальный формат', function (): void {
    expect(CreateImagePrompt::run('Fox in Tokyo'))
        ->toStartWith('Idea: Fox in Tokyo, Styles: photo-realistic')
        ->toContain('vertical composition', '9:16 aspect ratio');
});
