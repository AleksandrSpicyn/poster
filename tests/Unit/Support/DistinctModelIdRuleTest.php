<?php

declare(strict_types=1);

use App\Data\Episodes\PublishEpisodeData;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('проверяет каждый идентификатор и сохраняет адрес ошибки', function (): void {
    $input = ['channel_ids' => ['2', 2, 0, 'none', 3]];
    $rules = PublishEpisodeData::getValidationRules($input);

    $errors = Validator::make($input, $rules)->errors();

    expect($errors->keys())->toBe([
        'channel_ids.0',
        'channel_ids.1',
        'channel_ids.2',
        'channel_ids.3',
    ]);
});
