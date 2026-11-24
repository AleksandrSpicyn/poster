<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Enums\ProjectContentMode;
use App\Enums\SeriesOutputMode;
use App\Jobs\AdvanceProductionJob;
use App\Models\Channel;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('выключенный проект не запускается вручную', function (ProjectContentMode $mode): void {
    Bus::fake();
    $actor = serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create(['is_active' => false, 'content_mode' => $mode]);
    $series = $mode === ProjectContentMode::SERIES
        ? Series::factory()->recycle(workspaceOwner())->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]) : null;

    actingAs($actor)->postJson(route('production_runs.store', $project), ['request_key' => 'disabled-project', 'series_id' => $series?->id])
        ->assertUnprocessable()->assertJsonValidationErrors('project');

    expect($project->productionRuns()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
})->with([ProjectContentMode::SHORT, ProjectContentMode::SERIES]);

test('запускает сериал и сохраняет один выпуск при повторном запросе', function (): void {
    Bus::fake();
    $actor = serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->recycle(workspaceOwner())->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);
    $payload = ['request_key' => 'http-production', 'series_id' => $series->id];

    actingAs($actor)->post(route('production_runs.store', $project), $payload)->assertRedirect();
    actingAs($actor)->post(route('production_runs.store', $project), $payload)->assertRedirect();

    $run = ProductionRun::query()->sole();
    expect($run->series_id)->toBe($series->id);
    expect($run->actor_id)->toBe($actor->id);
    Bus::assertDispatchedTimes(AdvanceProductionJob::class, 1);
    Http::assertNothingSent();
});

test('не запускает сериал другого проекта', function (): void {
    Bus::fake();
    $project = Project::factory()->recycle(workspaceOwner())->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->recycle(workspaceOwner())->create(['automation_enabled' => true]);

    actingAs(serialOperator())->postJson(route('production_runs.store', $project), [
        'request_key' => 'wrong-project', 'series_id' => $series->id,
    ])->assertNotFound();

    expect(ProductionRun::query()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('не принимает пустой ключ выпуска', function (): void {
    Bus::fake();
    $project = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(serialOperator())->postJson(route('production_runs.store', $project), [])
        ->assertUnprocessable()->assertJsonValidationErrors('request_key');

    expect(ProductionRun::query()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('посторонний пользователь не запускает производство', function (): void {
    Bus::fake();
    serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(User::factory()->create())->postJson(route('production_runs.store', $project), ['request_key' => 'denied'])
        ->assertNotFound();

    expect(ProductionRun::query()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('гость не запускает производство', function (): void {
    Bus::fake();
    $project = Project::factory()->recycle(workspaceOwner())->create();

    post(route('production_runs.store', $project), ['request_key' => 'guest'])->assertRedirect(route('login'));

    expect(ProductionRun::query()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('запускает все сериалы проекта пакетно и не повторяет выпуск', function (int $size): void {
    Bus::fake();
    $actor = serialOperator();
    $project = Project::factory()->for($actor, 'owner')->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->for($project)->make(['automation_enabled' => true, 'automation_user_id' => $actor->id]);

    foreach (array_chunk(array_fill(0, $size, $series->getAttributes()), 100) as $batch) {
        Series::query()->insert($batch);
    }

    actingAs($actor);
    DB::enableQueryLog();

    try {
        post(route('production_runs.store', $project), ['request_key' => 'all-series'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $queries = collect(DB::getQueryLog())->pluck('query');
    } finally {
        DB::disableQueryLog();
    }

    post(route('production_runs.store', $project), ['request_key' => 'all-series'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($queries->filter(fn(mixed $sql): bool => is_string($sql) && str_starts_with($sql, 'select '))->count())->toBeLessThanOrEqual(12)
        ->and($queries->filter(fn(mixed $sql): bool => is_string($sql) && str_starts_with($sql, 'insert '))->count())->toBe((int) ceil($size / 100))
        ->and($project->productionRuns()->count())->toBe($size);
    Bus::assertDispatchedTimes(AdvanceProductionJob::class, $size);
})->with([1, 10, 100, 1000]);

test('сохраняет выбранных получателей и явный выпуск без отправки', function (SeriesOutputMode $mode, bool $empty): void {
    Bus::fake();
    $owner = serialOperator();
    $project = Project::factory()->for($owner, 'owner')->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->for($project)->create(['output_mode' => $mode, 'episode_limit' => 3, 'automation_enabled' => false, 'auto_publish' => false]);
    $channels = Channel::factory()->for($project)->count(2)->create(['type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'fake']]);
    $ids = $empty ? [] : [$channels->firstOrFail()->id];
    $payload = ['request_key' => 'selection', 'series_id' => $series->id, 'channel_ids' => $ids];

    actingAs($owner)->post(route('production_runs.store', $project), $payload)->assertRedirect()->assertSessionHasNoErrors();
    post(route('production_runs.store', $project), $payload)->assertRedirect()->assertSessionHasNoErrors();

    $run = $project->productionRuns()->sole();
    expect($run->deliveryChannelIds())->toBe($ids)
        ->and($run->input_snapshot['manual_delivery'] ?? null)->toBeTrue();
    Bus::assertDispatchedTimes(AdvanceProductionJob::class, 1);
    Http::assertNothingSent();
})->with(SeriesOutputMode::cases())->with([false, true]);

test('отклоняет недоступного получателя до создания выпуска и задания', function (bool $foreign): void {
    Bus::fake();
    $owner = serialOperator();
    $project = Project::factory()->for($owner, 'owner')->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->for($project)->create(['automation_enabled' => true]);
    $channel = Channel::factory()->for($project)->create([
        'owner_id' => $foreign ? User::factory()->create()->id : $owner->id,
        'type' => ChannelType::TIKTOK, 'credentials' => ['access_token' => 'fake', 'scope' => $foreign ? 'video.upload' : 'video.list'],
    ]);

    actingAs($owner)->postJson(route('production_runs.store', $project), [
        'request_key' => 'denied-channel', 'series_id' => $series->id, 'channel_ids' => [$channel->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('channel_ids');

    expect($project->productionRuns()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
})->with([true, false]);

test('отклоняет некорректные идентификаторы каналов до запуска', function (): void {
    Bus::fake();
    $actor = serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create();

    $response = actingAs($actor)
        ->postJson(route('production_runs.store', $project), [
            'request_key' => 'invalid-delivery-channels',
            'channel_ids' => [0, 'bad', 3, 3],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'channel_ids.0',
            'channel_ids.1',
            'channel_ids.2',
            'channel_ids.3',
        ]);
    expect($project->productionRuns()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});
