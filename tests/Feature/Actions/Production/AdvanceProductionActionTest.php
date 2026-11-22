<?php

declare(strict_types=1);

use App\Actions\Episodes\BuildEpisodeAssemblyAction;
use App\Actions\Episodes\FinishEpisodePublicationAction;
use App\Actions\Episodes\ProcessEpisodeScriptAction;
use App\Actions\Episodes\SubmitEpisodePublicationAction;
use App\Actions\Production\AdvanceProductionAction;
use App\Actions\Production\StartProductionAction;
use App\Actions\Shots\CheckShotGenerationAction;
use App\Actions\Shots\SubmitShotGenerationAction;
use App\Classes\Generators\ImageGenerator\ImageGenerator;
use App\Enums\AssemblyStatus;
use App\Enums\ChannelType;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\ProjectContentMode;
use App\Enums\PublicationStatus;
use App\Enums\RevisionStatus;
use App\Events\PostCreated;
use App\Jobs\GenerateEpisodeScriptJob;
use App\Models\Channel;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\Post;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use App\Models\Series;
use App\Tasks\Publications\ClaimPublication;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\StructuredAnonymousAgent;

use function Pest\Laravel\mock;
use function Pest\Laravel\travel;
use function Pest\Laravel\travelTo;
use function Tests\Support\acceptedEpisodePublication;
use function Tests\Support\episodeHttpFixture;
use function Tests\Support\serialOperator;
use function Tests\Support\serialReferenceMedia;
use function Tests\Support\storyScriptPayload;
use function Tests\Support\workspaceOwner;

test('очередь останавливает выпуск выключенного проекта', function (ProjectContentMode $mode): void {
    Bus::fake();
    Process::fake();
    $actor = serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create(['is_active' => false, 'content_mode' => $mode]);
    $series = $mode === ProjectContentMode::SERIES
        ? Series::factory()->recycle(workspaceOwner())->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]) : null;
    $run = ProductionRun::factory()->recycle(workspaceOwner())->for($project)->create(['series_id' => $series?->id, 'actor_id' => $actor->id]);

    app(AdvanceProductionAction::class)->run($run->id);

    expect($run->refresh()->status)->toBe(ProductionStatus::STOPPED);
    expect($run->error_message)->toBe('The project is disabled.');
    expect($project->prompts()->exists())->toBeFalse();
    expect($series?->episodes()->exists())->toBe($series !== null ? false : null);
    Bus::assertNothingDispatched();
    Process::assertNothingRan();
    Http::assertNothingSent();
})->with([ProjectContentMode::SHORT, ProjectContentMode::SERIES]);

test('неизвестный результат дубля останавливает конвейер без повторной оплаты', function (): void {
    $fixture = episodeHttpFixture();
    $series = $fixture['episode']->series()->firstOrFail();
    $project = $series->project()->firstOrFail();
    $project->update(['content_mode' => ProjectContentMode::SERIES]);

    $series->update(['automation_enabled' => true, 'automation_user_id' => $fixture['user']->id]);
    Channel::factory()->recycle(workspaceOwner())->for($project)->create(['type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'test']]);
    $prompt = Prompt::factory()->recycle(workspaceOwner())->for($project)->create(['shot_id' => $fixture['shot']->id]);
    $operation = $prompt->generationOperations()->create([
        'stage' => GenerationOperationStage::VIDEO, 'status' => GenerationOperationStatus::SUBMISSION_UNKNOWN,
        'request_key' => 'unknown-production', 'idempotency_key' => 'unknown-production', 'request_snapshot' => [],
    ]);
    $run = ProductionRun::factory()->recycle(workspaceOwner())->for($project)->for($series)->create([
        'actor_id' => $fixture['user']->id, 'episode_id' => $fixture['episode']->id,
        'status' => ProductionStatus::WAITING, 'stage' => ProductionStage::MEDIA,
    ]);
    Bus::fake();
    Process::fake();

    app(AdvanceProductionAction::class)->run($run->id);
    app(AdvanceProductionAction::class)->run($run->id);

    expect($run->refresh()->status)->toBe(ProductionStatus::NEEDS_ATTENTION);
    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUBMISSION_UNKNOWN);
    expect($fixture['shot']->prompts()->count())->toBe(1);
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
    Process::assertNothingRan();
});

test('непригодный сюжет останавливает выпуск до принятия и публикации', function (): void {
    $fixture = episodeHttpFixture();
    $series = $fixture['episode']->series()->firstOrFail();
    $project = $series->project()->firstOrFail();
    $project->update(['content_mode' => ProjectContentMode::SERIES]);

    $series->update(['automation_enabled' => true, 'automation_user_id' => $fixture['user']->id]);
    Channel::factory()->recycle(workspaceOwner())
        ->for($project)
        ->create(['type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'test']]);
    $assembly = EpisodeAssembly::factory()
        ->for($fixture['revision'], 'episodeRevision')
        ->ready()
        ->create();
    $outcome = [
        'usable' => false,
        'confirmed_events' => 'The cat repeatedly touches its scarf.',
        'character_state' => 'The cat remains in the same place.',
        'open_threads' => '',
        'scene_closed' => false,
        'review_notes' => 'The action loops without the planned visible payoff.',
    ];
    $run = ProductionRun::factory()->recycle(workspaceOwner())
        ->for($project)
        ->for($series)
        ->create([
            'actor_id' => $fixture['user']->id,
            'episode_id' => $fixture['episode']->id,
            'episode_assembly_id' => $assembly->id,
            'status' => ProductionStatus::WAITING,
            'stage' => ProductionStage::STORY,
            'outcome_snapshot' => $outcome,
        ]);
    Bus::fake();
    Process::fake();
    StructuredAnonymousAgent::fake()->preventStrayPrompts();

    app(AdvanceProductionAction::class)->run($run->id);
    app(AdvanceProductionAction::class)->run($run->id);

    expect($run->refresh()->status)->toBe(ProductionStatus::NEEDS_ATTENTION);
    expect($run->stage)->toBe(ProductionStage::STORY);
    expect($run->outcome_snapshot)->toEqual($outcome);
    expect($run->error_message)->toContain('The action loops without the planned visible payoff.');
    expect($assembly->refresh()->status)->toBe(AssemblyStatus::READY);
    expect($assembly->accepted_at)->toBeNull();
    expect($fixture['episode']->refresh()->accepted_assembly_id)->toBeNull();
    expect($series->refresh()->canonical_assembly_id)->toBeNull();
    expect($assembly->publications()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
    Process::assertNothingRan();
    StructuredAnonymousAgent::assertNeverPrompted();
    Http::assertNothingSent();
});

test('проводит выпуск до публикации и продолжает по фактической истории', function (): void {
    $disk = Storage::fake('local');
    Storage::fake('public');
    Bus::fake();
    Event::fake([PostCreated::class]);
    config(['services.video.provider' => 'seedance', 'services.seedance.api_key' => 'test']);
    $actor = serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->recycle(workspaceOwner())->for($project)->create([
        'automation_enabled' => true, 'automation_user_id' => $actor->id, 'episode_limit' => 1,
        'specification' => ['premise' => 'A cat discovers everyday objects.', 'visual_style' => 'animation',
            'language' => 'en', 'audience' => 'general', 'episode_duration_seconds' => 5,
            'audio_mode' => 'native', 'story_outline' => 'The cat learns to rest after its adventures.'],
    ]);
    $character = Character::factory()->for($series)->create();
    $appearance = CharacterRevision::factory()->for($character)->approved()->create();
    serialReferenceMedia($appearance);
    $character->update(['current_revision_id' => $appearance->id]);
    Channel::factory()->recycle(workspaceOwner())->for($project)->create(['type' => ChannelType::INSTAGRAM,
        'credentials' => ['access_token' => 'test-token', 'channel_id' => 'ig-production']]);
    $videoPath = $disk->path('provider-video.mp4');
    Process::run(['ffmpeg', '-v', 'error', '-nostdin', '-y', '-stream_loop', '-1',
        '-i', base_path('tests/Fixtures/shot.mp4'), '-t', '5', '-c', 'copy', $videoPath])->throw();
    Http::fake([
        '*contents/generations/tasks' => Http::response(['id' => 'production-video']),
        '*contents/generations/tasks/production-video' => Http::response(['status' => 'succeeded', 'generate_audio' => true,
            'content' => ['video_url' => 'https://cdn.example.com/production.mp4']]),
        'https://cdn.example.com/production.mp4' => Http::response(File::get($videoPath)),
        '*/ig-production/media' => Http::response(['id' => 'production-container']),
        '*/ig-production/media_publish' => Http::response(['id' => 'production-published']),
        '*/production-published*' => Http::response(['permalink' => 'https://instagram.com/p/production-published']),
    ]);
    mock(ImageGenerator::class)->shouldReceive('generate')->once()->andReturnUsing(function (string $prompt, array $references): string {
        expect($references)->toHaveCount(1);
        Storage::disk('public')->put('generated-images/production.png', File::get(base_path('tests/Fixtures/portrait.png')));

        return Storage::disk('public')->url('generated-images/production.png');
    });
    StructuredAnonymousAgent::fake([
        storyScriptPayload($appearance),
        ['usable' => true, 'confirmed_events' => 'The cat rests on an opened box.',
            'character_state' => 'The cat lies comfortably on the cardboard.', 'open_threads' => '',
            'scene_closed' => true, 'review_notes' => 'The final pose is clear.'],
    ])->preventStrayPrompts();
    $run = app(StartProductionAction::class)->run($project, 'complete-workflow', $actor)->sole();
    $advance = app(AdvanceProductionAction::class);

    $advance->run($run->id);
    $advance->run($run->id);

    $episode = $series->episodes()->sole();
    app(ProcessEpisodeScriptAction::class)->run($episode, $episode->script_request_key ?? throw new LogicException('Script request missing.'));
    $advance->run($run->id);
    $advance->run($run->id);

    $prompt = Prompt::query()->whereNotNull('shot_id')->sole();
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->where('stage', GenerationOperationStage::IMAGE)->sole()->id);
    $video = $prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->sole();
    app(SubmitShotGenerationAction::class)->run($video->id);
    travel(61)->seconds();
    app(CheckShotGenerationAction::class)->run($video->id);
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->where('stage', GenerationOperationStage::DOWNLOAD)->sole()->id);

    $advance->run($run->id);
    $assembly = $episode->refresh()->currentRevision?->assemblies()->sole() ?? throw new LogicException('Montage missing.');
    app(BuildEpisodeAssemblyAction::class)->run($assembly);
    $advance->run($run->id);
    $advance->run($run->id);
    $advance->run($run->id);

    $publication = $assembly->publications()->sole();
    $releaseAt = $publication->scheduled_at ?? throw new LogicException('Release time missing.');
    expect($run->refresh()->next_check_at?->equalTo($releaseAt))->toBeTrue();
    travelTo($releaseAt);
    $advance->run($run->id);
    expect($run->refresh()->status)->toBe(ProductionStatus::WAITING);

    app(SubmitEpisodePublicationAction::class)->run($publication);
    app(FinishEpisodePublicationAction::class)->run($publication);

    $advance->run($run->id);

    expect($run->refresh()->status)->toBe(ProductionStatus::COMPLETED);
    expect($prompt->generationOperations()->pluck('production_run_id')->all())->toBe([$run->id, $run->id, $run->id]);
    expect($publication->refresh()->production_run_id)->toBe($run->id);
    expect($episode->refresh()->title)->toBe('The locked door');
    expect($assembly->refresh()->status)->toBe(AssemblyStatus::ACCEPTED);
    expect($assembly->continuity_snapshot['confirmed_events'])->toBe('The cat rests on an opened box.');
    expect($assembly->continuity_snapshot['scene_closed'])->toBeTrue();
    expect($series->refresh()->canonical_assembly_id)->toBe($assembly->id);
    expect(Post::query()->where('publication_request_id', $publication->id)->sole()->episode_assembly_id)->toBe($assembly->id);
    expect(app(StartProductionAction::class)->run($project, 'limit-reached', $actor))->toBeEmpty();
    Http::assertSentCount(6);
    Event::assertDispatchedTimes(PostCreated::class, 1);
    StructuredAnonymousAgent::assertPromptedTimes(2);
    StructuredAnonymousAgent::assertPrompted(fn(AgentPrompt $prompt): bool => count($prompt->attachments) === 6);

    $series->update(['episode_limit' => 2]);
    $nextRun = app(StartProductionAction::class)->run($project, 'next-episode', $actor)->sole();
    $advance->run($nextRun->id);
    $advance->run($nextRun->id);

    $next = Episode::query()->where('series_id', $series->id)->where('number', 2)->sole();
    expect(data_get($next->script_request_payload, 'context.continuity.confirmed_events'))->toBe('The cat rests on an opened box.');
    expect(data_get($next->script_request_payload, 'context.source_assembly_id'))->toBe($assembly->id);
    expect($next->predecessor_episode_id)->toBe($episode->id);
    expect(Prompt::query()->count())->toBe(1);
    expect($prompt->refresh()->operations->every(fn($operation): bool => $operation->status === GenerationOperationStatus::SUCCEEDED))->toBeTrue();
});

test('ожидает персонажей без запуска платной генерации', function (): void {
    Bus::fake();
    Http::fake();
    $actor = serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create(['content_mode' => ProjectContentMode::SERIES]);
    Channel::factory()->recycle(workspaceOwner())->for($project)->create(['type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'test']]);
    $series = Series::factory()->recycle(workspaceOwner())->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);
    $run = app(StartProductionAction::class)->run($project, 'no-character', $actor, $series->id)->sole();

    app(AdvanceProductionAction::class)->run($run->id);

    expect($run->refresh()->status)->toBe(ProductionStatus::WAITING);
    expect($run->stage)->toBe(ProductionStage::PREPARING);
    expect($series->episodes()->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('сам утверждает загруженные референсы и создаёт следующий эпизод', function (): void {
    Storage::fake('local');
    Bus::fake();
    $actor = serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create(['content_mode' => ProjectContentMode::SERIES]);
    Channel::factory()->recycle(workspaceOwner())->for($project)->create(['type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'test']]);
    $series = Series::factory()->recycle(workspaceOwner())->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);
    $character = Character::factory()->for($series)->create();
    $revision = CharacterRevision::factory()->for($character)->create();
    serialReferenceMedia($revision);
    $run = app(StartProductionAction::class)->run($project, 'characters-ready', $actor, $series->id)->sole();

    travel(121)->seconds();
    app(AdvanceProductionAction::class)->run($run->id);
    expect($run->refresh()->stage)->toBe(ProductionStage::SCREENPLAY);
    app(AdvanceProductionAction::class)->run($run->id);

    expect($revision->refresh()->status)->toBe(RevisionStatus::APPROVED);
    expect($character->refresh()->current_revision_id)->toBe($revision->id);
    expect($series->episodes()->sole()->number)->toBe(1);
    expect($run->refresh()->stage)->toBe(ProductionStage::SCREENPLAY);
    Bus::assertDispatched(GenerateEpisodeScriptJob::class);
});

test('пауза сериала останавливает следующие этапы', function (): void {
    Bus::fake();
    $actor = serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->recycle(workspaceOwner())->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);
    $run = app(StartProductionAction::class)->run($project, 'pause', $actor, $series->id)->sole();
    $series->update(['automation_enabled' => false]);

    app(AdvanceProductionAction::class)->run($run->id);

    expect($run->refresh()->status)->toBe(ProductionStatus::STOPPED);
    expect($series->episodes()->exists())->toBeFalse();
});

test('неподключённый или выключенный канал задерживает производство', function (bool $enabled, array $credentials): void {
    Bus::fake();
    Process::fake();
    $actor = serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create();
    Channel::factory()->recycle(workspaceOwner())->for($project)->create(['type' => ChannelType::YOUTUBE, 'is_active' => $enabled, 'credentials' => $credentials]);
    $run = ProductionRun::factory()->recycle(workspaceOwner())->for($project)->create(['actor_id' => $actor->id]);

    app(AdvanceProductionAction::class)->run($run->id);

    expect($run->refresh()->status)->toBe(ProductionStatus::WAITING);
    expect($project->prompts()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
    Process::assertNothingRan();
    Http::assertNothingSent();
})->with([
    'выключен' => [false, ['access_token' => 'test']],
    'без токена' => [true, ['channel_id' => 'account-only']],
]);

test('отправляет готовый эпизод только выбранным каналам', function (bool $deliver): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $owner] = acceptedEpisodePublication();
    $episode = $assembly->episodeRevision()->firstOrFail()->episode()->firstOrFail();
    $series = $episode->series()->firstOrFail();
    $project = $series->project()->firstOrFail();
    $project->update(['content_mode' => ProjectContentMode::SERIES]);

    $series->update(['automation_enabled' => true, 'auto_publish' => false]);
    Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'fake']]);
    $ids = $deliver ? [$channel->id] : [];
    $run = ProductionRun::factory()->for($project)->for($series)->create([
        'episode_id' => $episode->id, 'episode_assembly_id' => $assembly->id, 'actor_id' => $owner->id,
        'stage' => ProductionStage::PUBLICATION, 'input_snapshot' => ['channel_ids' => $ids, 'manual_delivery' => true],
    ]);
    Bus::fake();

    app(AdvanceProductionAction::class)->run($run->id);

    expect(PublicationRequest::query()->pluck('channel_id')->all())->toBe($ids)
        ->and($run->refresh()->status)->toBe($deliver ? ProductionStatus::WAITING : ProductionStatus::COMPLETED);

    if ($deliver) {
        $request = PublicationRequest::query()->sole();
        $request->update(['scheduled_at' => now()->subMinute()]);
        $project->channels()->detach($channel);
        expect(app(ClaimPublication::class)->run($request))->toBeFalse()
            ->and($request->refresh()->status)->toBe(PublicationStatus::FAILED);
    } else {
        Bus::assertNothingDispatched();
    }

    Http::assertNothingSent();
})->with([true, false]);

test('закрепляет пустой выбор в самом эпизоде перед подготовкой сценария', function (): void {
    Storage::fake('local');
    ['episode' => $episode, 'user' => $owner] = episodeHttpFixture();
    $series = $episode->series()->firstOrFail();
    $project = $series->project()->firstOrFail();
    $project->update(['content_mode' => ProjectContentMode::SERIES]);

    $series->update(['automation_enabled' => false, 'auto_publish' => false]);
    Bus::fake();
    $run = app(StartProductionAction::class)->run($project, 'manual-episode', $owner, $series->id, [])->sole();

    app(AdvanceProductionAction::class)->run($run->id);

    expect($run->refresh()->episode_id)->toBe($episode->id)
        ->and($run->stage)->toBe(ProductionStage::SCREENPLAY)
        ->and($episode->refresh()->delivery_channel_ids)->toBe([]);
    Http::assertNothingSent();
});
