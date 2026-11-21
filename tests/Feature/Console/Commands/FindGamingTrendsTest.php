<?php

declare(strict_types=1);

use App\Models\Project;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Tests\Support\consoleCommand;

test('ищет короткие игровые видео по названию игры и публичным просмотрам', function (): void {
    Project::factory()->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    config(['services.youtube.data_api_key' => 'test']);
    Http::fake([
        '*/search*' => Http::response(['items' => [['id' => ['videoId' => '12345678901']]]]),
        '*/videos*' => Http::response(['items' => [['id' => '12345678901', 'snippet' => ['title' => 'Gameplay', 'channelTitle' => 'Gaming', 'publishedAt' => now()->subHours(10)->toIso8601String()], 'statistics' => ['viewCount' => '125']]]]),
    ]);

    consoleCommand('opus:trends', ['game' => 'GTA 6'])
        ->expectsTable(['Title', 'Channel', 'Views', 'Views/day (min. 1 day)', 'URL'], [['Gameplay', 'Gaming', '125', 125, 'https://www.youtube.com/watch?v=12345678901']])
        ->assertSuccessful();

    consoleCommand('opus:trends')->assertSuccessful();
    Http::assertSentCount(4);

    Http::assertSent(fn(Request $request): bool => ($request['q'] ?? null) === 'GTA 6' && $request['videoDuration'] === 'short'
        && $request['order'] === 'viewCount' && $request->hasHeader('X-Goog-Api-Key', 'test'));
});
