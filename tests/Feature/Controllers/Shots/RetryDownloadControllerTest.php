<?php

declare(strict_types=1);

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Jobs\SubmitShotGenerationJob;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\Scene;
use App\Models\Series;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('повтор загрузки требует входа', function (): void {
    ['params' => $params, 'operation' => $operation] = retryShotDownloadRouteFixture();
    Http::fake();
    Bus::fake();

    post(route('shots.download.retry', $params))->assertRedirect(route('login'));

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::FAILED);
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});

test('неподтверждённый пользователь не возобновляет загрузку', function (): void {
    ['params' => $params, 'operation' => $operation] = retryShotDownloadRouteFixture();
    $actor = User::factory()->unverified()->create();
    Http::fake();
    Bus::fake();

    actingAs($actor)->postJson(route('shots.download.retry', $params))->assertForbidden();

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::FAILED);
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});

test('оператор возобновляет существующую загрузку без повторной генерации', function (): void {
    ['params' => $params, 'operation' => $operation, 'actor' => $actor] = retryShotDownloadRouteFixture();
    Http::fake();
    Bus::fake();

    actingAs($actor)->post(route('shots.download.retry', $params))
        ->assertRedirect(route('episodes.show', ['series' => $params['series'], 'episode' => $params['episode']]) . '#shots');

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::PLANNED);
    expect($operation->check_count)->toBe(3);
    expect(data_get($operation->result_snapshot, 'retry_limit'))->toBe(6);
    expect(data_get($operation->result_snapshot, 'retry_requested_by'))->toBe($actor->id);
    expect(Prompt::query()->count())->toBe(1);
    Bus::assertDispatched(SubmitShotGenerationJob::class, fn(SubmitShotGenerationJob $job): bool => $job->operationId === $operation->id);
    Http::assertNothingSent();
});

test('дубль другого плана не проходит вложенный scope', function (): void {
    ['params' => $params, 'operation' => $operation, 'actor' => $actor] = retryShotDownloadRouteFixture();
    $params['prompt'] = Prompt::factory()->recycle(workspaceOwner())->create()->id;
    Http::fake();
    Bus::fake();

    actingAs($actor)->postJson(route('shots.download.retry', $params))->assertNotFound();

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::FAILED);
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});

test('устаревший контекст запрещает возобновление', function (): void {
    ['params' => $params, 'operation' => $operation, 'actor' => $actor] = retryShotDownloadRouteFixture();
    Series::query()->whereKey($params['series'])->increment('specification_version');
    Http::fake();
    Bus::fake();

    actingAs($actor)->postJson(route('shots.download.retry', $params))->assertUnprocessable()->assertInvalid(['shot']);

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::FAILED);
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});

/** @return array{actor: User, operation: GenerationOperation, params: array<string, int>} */
function retryShotDownloadRouteFixture(): array
{
    $actor = serialOperator();
    $series = Series::factory()->recycle(workspaceOwner())->create();
    $episode = Episode::factory()->recycle(workspaceOwner())->for($series)->create();
    $revision = EpisodeRevision::factory()->for($episode)->approved()->create(['context_snapshot' => ['specification_version' => 1]]);
    $episode->update(['current_revision_id' => $revision->id]);
    $scene = Scene::factory()->for($revision, 'episodeRevision')->create();
    $shot = Shot::factory()->for($scene)->create();
    $prompt = Prompt::factory()->recycle(workspaceOwner())->for($shot)->create(['project_id' => $series->project_id, 'take_number' => 1]);
    $operation = $prompt->generationOperations()->create([
        'stage' => GenerationOperationStage::DOWNLOAD, 'status' => GenerationOperationStatus::FAILED,
        'request_key' => 'download-http', 'idempotency_key' => hash('sha256', 'download-http'), 'request_snapshot' => [],
        'result_snapshot' => ['video_url' => 'https://cdn.example.com/same-video.mp4'], 'check_count' => 3,
    ]);

    return ['actor' => $actor, 'operation' => $operation, 'params' => [
        'series' => $series->id, 'episode' => $episode->id, 'revision' => $revision->id,
        'scene' => $scene->id, 'shot' => $shot->id, 'prompt' => $prompt->id,
    ]];
}

test('подтверждённый пользователь без операторского доступа не повторяет загрузку', function (): void {
    ['params' => $params, 'operation' => $operation] = retryShotDownloadRouteFixture();
    $actor = User::factory()->create();
    Http::fake();
    Bus::fake();

    actingAs($actor)->postJson(route('shots.download.retry', $params))->assertNotFound();

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::FAILED);
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});
