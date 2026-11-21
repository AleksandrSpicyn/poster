<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\OpusClip;
use App\Models\OpusSource;
use App\Models\Project;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\assertDatabaseCount;
use function Tests\Support\consoleCommand;

test('импортирует исходник, готовые клипы и обе ссылки без повторной публикации', function (): void {
    $project = Project::factory()->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE, 'is_active' => false]);
    $other = Project::factory()->create(['name' => $project->name]);
    config(['services.opus_clip.key' => 'fake-key']);
    Http::fake([
        '*/exportable-clips*' => Http::response(['data' => [['id' => 'source.A', 'title' => 'Boss fight', 'score' => 91, 'durationMs' => 23400], ['id' => 'source.B']]]),
        '*/post-tasks*' => Http::response(['data' => ['data' => [
            ['projectId' => 'source', 'clipId' => 'A', 'platform' => 'YOUTUBE', 'status' => 'PUBLISHED', 'extVideoLink' => 'https://youtu.be/12345678901'],
            ['projectId' => 'source', 'clipId' => 'source.A', 'platform' => 'INSTAGRAM', 'status' => 'PUBLISHED', 'extVideoLink' => 'https://www.instagram.com/reel/test/'],
            ['projectId' => 'foreign', 'clipId' => 'C', 'platform' => 'YOUTUBE'],
        ]]]),
    ]);

    consoleCommand('opus:sync', ['--preview' => true, '--source' => 'source'])->expectsOutput('source: Found 2 clips, 1 with public links.')->assertSuccessful();
    assertDatabaseCount('channels', 0);
    assertDatabaseCount('projects', 2);
    consoleCommand('opus:sync', ['--source' => 'source'])->assertSuccessful();
    consoleCommand('opus:sync', ['--source' => 'source'])->assertSuccessful();

    $clip = OpusClip::query()->where('clip_id', 'A')->sole();
    expect($project->id)->not->toBe($other->id)
        ->and($project->is_active)->toBeFalse()
        ->and($project->channels()->count())->toBe(0)
        ->and($other->channels()->count())->toBe(0)
        ->and($clip->source->project_id)->toBe($project->id)
        ->and($clip->publications)->toHaveCount(2)
        ->and($clip->publications[1]['url'])->toBe('https://www.instagram.com/reel/test/');
    assertDatabaseCount('opus_clips', 2);
    assertDatabaseCount('posts', 0);
    assertDatabaseCount('publication_requests', 0);
    assertDatabaseCount('channels', 0);
    expect(Channel::query()->whereNotNull('credentials')->count())->toBe(0);
    Http::assertSentCount(6);

    foreach (Http::recorded() as [$request]) {
        expect($request)->toBeInstanceOf(Request::class)
            ->and($request->method())->toBe('GET')
            ->and($request->hasHeader('Authorization', 'Bearer fake-key'))->toBeTrue()
            ->and($request['projectId'])->toBe('source')
            ->and($request['q'])->toBe('findByProjectId');
    }
});

test('сбой истории не затирает ранее импортированный клип', function (): void {
    config(['services.opus_clip.key' => 'fake-key']);
    $project = Project::factory()->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    $clip = OpusClip::factory()->for(OpusSource::factory()->for($project), 'source')->create(['clip_id' => 'C', 'title' => 'Original']);
    Http::fake([
        '*/exportable-clips*' => Http::response([['id' => 'C', 'title' => 'Changed']]),
        '*/post-tasks*' => Http::response(['unexpected' => true]),
    ]);

    consoleCommand('opus:sync', ['--source' => 'source'])->assertFailed();

    expect($clip->refresh()->title)->toBe('Original');
    assertDatabaseCount('channels', 0);
});

test('предпросмотр обходит проекты и сохраняет одинаковые ID клипов у разных игр', function (): void {
    config(['services.opus_clip.key' => 'fake']);
    Http::fake([
        '*/clip-projects*' => Http::response(['list' => [
            ['projectId' => 'fixture-source', 'title' => 'GTA 6'], ['projectId' => 'other', 'title' => 'Another game'],
            ['projectId' => 'deleted', 'isDeleted' => true],
        ]]),
        '*/exportable-clips*' => Http::response([['id' => 'A']]),
        '*/post-tasks*' => Http::response(['data' => ['data' => []]]),
    ]);

    consoleCommand('opus:sync', ['--preview' => true])
        ->expectsOutput('fixture-source: Found 1 clips, 0 with public links.')
        ->expectsOutput('other: Found 1 clips, 0 with public links.')
        ->assertSuccessful();

    Http::assertSentCount(5);
});

test('создаёт по проекту на исходник и сохраняет общие привязки каналов', function (): void {
    $project = Project::factory()->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE, 'external_account' => 'fixture-youtube']);
    Channel::factory()->for($project)->create(['type' => ChannelType::INSTAGRAM, 'external_account' => 'fixture_creator']);
    config(['services.opus_clip.key' => 'fake']);
    Http::fake([
        '*/exportable-clips*' => Http::response([['id' => 'A']]),
        '*/post-tasks*' => Http::response(['data' => ['data' => []]]),
    ]);

    consoleCommand('opus:sync', ['--source' => 'fixture-source', '--game' => 'Fixture game'])->assertSuccessful();
    consoleCommand('opus:sync', ['--source' => 'new-game', '--game' => 'Next game'])->assertSuccessful();
    consoleCommand('opus:sync', ['--source' => 'new-game'])->assertSuccessful();

    assertDatabaseCount('projects', 3);
    expect(Project::query()->whereNotNull('opus_source_id')->orderBy('id')->pluck('name')->all())->toBe(['Fixture game', 'Next game']);
    assertDatabaseCount('channels', 2);
    assertDatabaseCount('channel_project', 2);
    assertDatabaseCount('opus_clips', 2);
    expect(OpusSource::query()->orderBy('id')->pluck('game')->all())->toBe(['Fixture game', 'Next game']);
});

test('ошибка ключа в предварительном просмотре не раскрывает ответ провайдера', function (): void {
    config(['services.opus_clip.key' => 'test']);
    Http::fake(['*/exportable-clips*' => Http::response(['message' => 'private diagnostic'], 403)]);

    consoleCommand('opus:sync', ['--source' => 'P1', '--preview' => true])
        ->expectsOutput('P1: OpusClip exportable-clips returned HTTP 403.')
        ->doesntExpectOutputToContain('private diagnostic')
        ->assertFailed();

    Http::assertSentCount(1);
});

test('пустые проекты с одинаковым названием создаются отдельно, исторический импорт сохраняется', function (): void {
    $workspace = Project::factory()->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    OpusSource::factory()->for($workspace)->create(['external_id' => 'historical']);
    config(['services.opus_clip.key' => 'fake']);
    Http::fake([
        '*/clip-projects*' => Http::response(['list' => [
            ['projectId' => 'historical', 'title' => 'Old'],
            ['projectId' => 'new-a', 'title' => 'Same'],
            ['projectId' => 'new-b', 'title' => 'Same'],
        ]]),
        '*/exportable-clips*' => Http::response([]),
        '*/post-tasks*' => Http::response(['data' => ['data' => []]]),
    ]);

    consoleCommand('opus:sync')->assertSuccessful();
    consoleCommand('opus:sync')->assertSuccessful();

    expect(Project::query()->where('name', 'Same')->count())->toBe(2);
    assertDatabaseCount('projects', 3);
    assertDatabaseCount('opus_sources', 3);
    assertDatabaseCount('opus_clips', 0);
});
