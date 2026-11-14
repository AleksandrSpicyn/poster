<?php

declare(strict_types=1);

use App\Classes\Generators\AudioGenerator\SunoUnofficial\SunoUnofficial;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Http;

beforeEach(/** @throws Exception */ function (): void {
    config([
        'services.suno_unofficial.api_url' => 'https://apibox.erweima.ai/api/v1',
        'services.hailuo.api_key' => 'test', 'services.hailuo.group_id' => 'group',
        'services.runway.api_key' => 'test', 'services.suno_unofficial.api_key' => 'test',
    ]);

    GeneralSettings::fake(['duration' => 10]);
});

test('ошибка генератора не маскируется успешным результатом', /** @throws Exception */ function (): void {
    Http::fake(['https://apibox.erweima.ai/api/v1/*' => Http::response(['message' => 'Unavailable'], 503)]);

    $client = app(SunoUnofficial::class);

    expect(fn() => $client->generate('image', 'idea'))->toThrow(RuntimeException::class);
    Http::assertSentCount(1);
});

test('генератор отклоняет успешный HTTP с отсутствующим идентификатором', /** @throws Exception */ function (): void {
    config(['services.hailuo.api_key' => 'test', 'services.hailuo.group_id' => 'test', 'services.runway.api_key' => 'test', 'services.suno_unofficial.api_key' => 'test']);

    GeneralSettings::fake(['duration' => 10]);
    Http::fake(['https://apibox.erweima.ai/api/v1/*' => Http::response([])]);

    $client = app(SunoUnofficial::class);

    expect(fn() => $client->generate('image', 'idea'))->toThrow(UnexpectedValueException::class);
    Http::assertSentCount(1);
});
