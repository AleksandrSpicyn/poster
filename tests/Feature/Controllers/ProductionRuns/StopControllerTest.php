<?php

declare(strict_types=1);

use App\Actions\Episodes\FinishEpisodePublicationAction;
use App\Actions\Episodes\SubmitEpisodePublicationAction;
use App\Actions\Shots\CheckShotGenerationAction;
use App\Actions\Shots\SubmitShotGenerationAction;
use App\Data\Episodes\PublishEpisodeData;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\ProductionStatus;
use App\Enums\PublicationStatus;
use App\Enums\VideoProvider;
use App\Models\Post;
use App\Models\ProductionRun;
use App\Models\Prompt;
use App\Models\User;
use App\Tasks\Publications\CreatePublicationRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Tests\Support\acceptedEpisodePublication;
use function Tests\Support\episodeHttpFixture;
use function Tests\Support\serialOperator;

test('остановка не позволяет поставить готовый контейнер на публикацию', function (): void {
    Storage::fake('local');
    Bus::fake();
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    $run = ProductionRun::factory()->create(['project_id' => $channel->project_id, 'episode_assembly_id' => $assembly->id]);
    $publication = app(CreatePublicationRequest::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user, $run)->sole();
    $publication->update(['status' => PublicationStatus::PROCESSING, 'container_id' => 'already-uploaded']);

    Bus::fake();

    actingAs($user)->post(route('production_runs.stop', $run))->assertRedirect();

    expect($publication->refresh()->status)->toBe(PublicationStatus::FAILED);
    expect($publication->container_id)->toBe('already-uploaded');
    expect($run->refresh()->status)->toBe(ProductionStatus::STOPPED);
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

test('остановленный выпуск блокирует задержавшуюся публикацию', function (bool $container): void {
    Storage::fake('local');
    Bus::fake();
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    $episode = $assembly->episodeRevision->episode ?? throw new LogicException('Episode missing.');
    $series = $episode->series()->firstOrFail();
    $series->update(['automation_enabled' => true]);

    $run = ProductionRun::factory()->for($series)->create([
        'project_id' => $channel->project_id, 'episode_assembly_id' => $assembly->id, 'episode_id' => $episode->id,
    ]);

    actingAs($user)->post(route('production_runs.stop', $run))->assertRedirect();
    $publication = app(CreatePublicationRequest::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user, $run)->sole();

    if ($container) {
        $publication->update(['status' => PublicationStatus::PROCESSING, 'container_id' => 'late-container']);
        app(FinishEpisodePublicationAction::class)->run($publication);
    } else {
        app(SubmitEpisodePublicationAction::class)->run($publication);
    }

    expect($publication->refresh()->status)->toBe(PublicationStatus::FAILED);
    expect($publication->error_message)->toBe('Automatic production stopped before publication.');
    expect(Post::query()->where('episode_assembly_id', $assembly->id)->exists())->toBeFalse();
    expect($series->refresh()->automation_enabled)->toBeTrue();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
})->with(['отправка видео' => false, 'готовый контейнер' => true]);

test('остановка сохраняет поздний оплаченный результат без принятия дубля', function (): void {
    Storage::fake('local');
    Bus::fake();
    config(['services.seedance.api_key' => 'test']);
    ['episode' => $episode, 'shot' => $shot, 'user' => $user] = episodeHttpFixture();
    $series = $episode->series()->firstOrFail();
    $run = ProductionRun::factory()->for($series)->create([
        'project_id' => $series->project_id, 'episode_id' => $episode->id,
    ]);
    $prompt = Prompt::factory()->create(['project_id' => $series->project_id, 'shot_id' => $shot->id]);
    $operation = $prompt->generationOperations()->create([
        'production_run_id' => $run->id, 'stage' => GenerationOperationStage::VIDEO,
        'status' => GenerationOperationStatus::SUBMITTED, 'provider' => VideoProvider::SEEDANCE,
        'external_id' => 'stopped-remote', 'request_key' => 'stopped-receipt',
        'idempotency_key' => 'stopped-receipt', 'request_snapshot' => [],
    ]);
    Http::fake([
        '*contents/generations/tasks/stopped-remote' => Http::response(['status' => 'succeeded', 'generate_audio' => false,
            'content' => ['video_url' => 'https://cdn.example.com/stopped.mp4']]),
        'https://cdn.example.com/stopped.mp4' => Http::response(File::get(base_path('tests/Fixtures/shot.mp4'))),
    ]);

    actingAs($user)->post(route('production_runs.stop', $run))->assertRedirect();
    app(CheckShotGenerationAction::class)->run($operation->id);
    $download = $prompt->generationOperations()->where('stage', GenerationOperationStage::DOWNLOAD)->sole();
    app(SubmitShotGenerationAction::class)->run($download->id);

    expect($run->refresh()->status)->toBe(ProductionStatus::STOPPED);
    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::CANCELLED);
    expect($download->refresh()->status)->toBe(GenerationOperationStatus::CANCELLED);
    expect($download->production_run_id)->toBe($run->id);
    expect($prompt->refresh()->generated)->toBeFalse();
    expect($prompt->getFirstMedia(Prompt::SHOT_VIDEO)?->disk)->toBe('local');
    expect(Post::query()->exists())->toBeFalse();
    Http::assertSentCount(2);
});

test('остановка сохраняет выпуск и не запускает следующие этапы', function (): void {
    Bus::fake();
    $run = ProductionRun::factory()->create();

    actingAs(serialOperator())->post(route('production_runs.stop', $run))->assertRedirect();

    expect($run->refresh()->status)->toBe(ProductionStatus::STOPPED);
    expect($run->next_check_at)->toBeNull();
    expect($run->completed_at)->not->toBeNull();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

test('посторонний пользователь не останавливает выпуск', function (): void {
    Bus::fake();
    serialOperator();
    $run = ProductionRun::factory()->create();

    actingAs(User::factory()->create())->postJson(route('production_runs.stop', $run))->assertForbidden();

    expect($run->refresh()->status)->toBe(ProductionStatus::QUEUED);
    Bus::assertNothingDispatched();
});
