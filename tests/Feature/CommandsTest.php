<?php

declare(strict_types=1);

use App\Classes\Generators\AudioGenerator\SunoUnofficial\SunoUnofficial;
use App\Classes\Generators\VideoGenerator\Hailuo\Hailuo;
use App\Classes\Generators\VideoGenerator\Runway\Runway;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Prompt;
use App\Settings\GeneralSettings;
use Google\Client;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use function Pest\Laravel\assertDatabaseCount;

test('TikTok сохраняет токен после успешного обмена кода', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::TIKTOK]);
    Http::fake(['https://open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'test-access', 'refresh_token' => 'test-refresh'])]);

    consoleCommand('tiktok:auth', ['channel_id' => $channel->id])->expectsQuestion('Paste the returned ?code=', 'test-code')->assertSuccessful();

    expect($channel->refresh()->credentials)->toBe(['access_token' => 'test-access', 'refresh_token' => 'test-refresh']);
    Http::assertSent(fn(Request $request): bool => $request['code'] === 'test-code');
});

test('TikTok не меняет credentials при отказе API', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::TIKTOK]);
    Http::fake(['https://open.tiktokapis.com/v2/oauth/token/' => Http::response([], 400)]);

    consoleCommand('tiktok:auth', ['channel_id' => $channel->id])->expectsQuestion('Paste the returned ?code=', 'test-code')->assertFailed();

    expect($channel->refresh()->credentials)->toBeNull();
    Http::assertSentCount(1);
});

test('YouTube сохраняет токен после обмена OAuth кода', /** @throws Exception */ function (bool $failure): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE]);
    $handler = new MockHandler([new Response($failure ? 400 : 200, [], json_encode($failure
        ? ['error' => 'invalid_grant', 'error_description' => 'Invalid code']
        : ['access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'expires_in' => 3600], JSON_THROW_ON_ERROR))]);
    $client = new Client();
    $client->setHttpClient(new HttpClient(['handler' => HandlerStack::create($handler), 'http_errors' => false]));

    app()->instance(Client::class, $client);

    consoleCommand('youtube:auth', ['channel_id' => $channel->id])
        ->expectsQuestion('Paste the authorization code from the browse', 'test-code')->assertExitCode($failure ? 1 : 0);

    expect($channel->refresh()->credentials['access_token'] ?? null)->toBe($failure ? null : 'test-access')
        ->and($handler->count())->toBe(0);
})->with([false, true]);

test('команда сборки пропускает идеи без исходных файлов', function (): void {
    $prompt = Prompt::factory()->create();

    consoleCommand('app:save-video')->assertSuccessful();

    expect($prompt->refresh()->generated)->toBeFalse();
    assertDatabaseCount('media', 0);
});

test('команда генерации без проектов не выполняет внешних запросов', function (): void {
    Http::fake();
    consoleCommand('app:make-short')->assertSuccessful();
    Http::assertNothingSent();
    assertDatabaseCount('prompts', 0);
});

test('генератор отклоняет успешный HTTP с отсутствующим идентификатором', /** @throws Exception */ function (string $generator): void {
    config(['services.hailuo.api_key' => 'test', 'services.hailuo.group_id' => 'test', 'services.runway.api_key' => 'test', 'services.suno_unofficial.api_key' => 'test']);
    GeneralSettings::fake(['duration' => 10]);
    Http::fake(['*' => Http::response([])]);
    /** @var Hailuo|Runway|SunoUnofficial $client */
    $client = app($generator);

    expect(fn() => $client->generate('image', 'idea'))->toThrow(UnexpectedValueException::class);
    Http::assertSentCount(1);
})->with([Hailuo::class, Runway::class, SunoUnofficial::class]);
