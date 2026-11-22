<?php

declare(strict_types=1);

use App\Jobs\RefreshClippingSource;
use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Models\OpusSource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ArchiveClippingReader;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

test('сохраняет область подключения и отклоняет старый предпросмотр', function (): void {
    $connection = ClippingConnection::factory()->create();
    $preview = ClippingImport::query()->create(['clipping_connection_id' => $connection->id, 'external_id' => 'race', 'clips' => [], 'clip_count' => 0]);
    $scope = ['import_projects' => true, 'import_accounts' => false, 'import_archives' => false];
    actingAs(User::query()->findOrFail($connection->owner_id));

    patch(route('clipping.scope.update', $connection), $scope)->assertRedirect(route('clipping.sources', $connection));
    patch(route('clipping.scope.update', $connection), $scope)->assertRedirect();

    post(route('clipping.import', [$connection, $preview]))->assertSessionHasErrors('project_id');
    post(route('clipping.preview', $connection), ['external_id' => 'race', 'accounts' => [['id' => 'account']]])->assertSessionHasErrors('accounts');

    expect($connection->refresh()->scope_version)->toBe(1)->and($preview->refresh()->imported_at)->toBeNull();
    assertDatabaseCount('opus_sources', 0);
    Http::assertNothingSent();
});

test('при выборе только аккаунтов импортирует связи без запроса проектов и клипов', function (): void {
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $scope = ['import_projects' => false, 'import_accounts' => true, 'import_archives' => false];
    Http::fake(['*/social-accounts*' => Http::response(['data' => [['postAccountId' => 'account', 'platform' => 'YOUTUBE', 'extUserId' => 'channel', 'extUserName' => 'Автор']]])]);
    actingAs($owner);

    patch(route('clipping.scope.update', $connection), $scope)->assertRedirect();
    get(route('clipping.sources', $connection))->assertOk();
    post(route('clipping.preview', $connection), ['project_id' => $project->id, 'accounts' => [['id' => 'account', 'channel_id' => null]]])->assertSessionHasNoErrors();
    $preview = ClippingImport::query()->sole();
    post(route('clipping.import', [$connection, $preview]))->assertRedirect(route('projects.show', $project));
    post(route('clipping.import', [$connection, $preview]))->assertRedirect(route('projects.show', $project));

    expect($project->channels()->count())->toBe(1)->and($project->refresh()->clipping_connection_id)->toBeNull();
    assertDatabaseCount('opus_sources', 0);
    assertDatabaseCount('opus_clips', 0);
    Http::assertSentCount(2);
});

test('отклоняет архивы OpusClip и изменение области другого владельца', function (): void {
    $connection = ClippingConnection::factory()->create();
    $scope = ['import_projects' => true, 'import_accounts' => true, 'import_archives' => true];
    actingAs(User::query()->findOrFail($connection->owner_id));

    patch(route('clipping.scope.update', $connection), $scope)->assertSessionHasErrors('import_archives');
    actingAs(User::factory()->create());
    patch(route('clipping.scope.update', $connection), $scope)->assertNotFound();

    expect($connection->refresh()->import_archives)->toBeFalse();
    Http::assertNothingSent();
});

test('поддерживающий драйвер показывает архив только после выбора и сохраняет историю при отключении', function (): void {
    config(['clipping.drivers.archive' => ['label' => 'Архив', 'reader' => ArchiveClippingReader::class]]);
    $connection = ClippingConnection::factory()->create(['driver' => 'archive']);
    $scope = ['import_projects' => true, 'import_accounts' => false, 'import_archives' => true];
    actingAs(User::query()->findOrFail($connection->owner_id));

    post(route('clipping.preview', $connection), ['external_id' => 'archived'])->assertSessionHasErrors('external_id');
    patch(route('clipping.scope.update', $connection), $scope)->assertRedirect();
    get(route('clipping.sources', $connection))->assertInertia(fn(Assert $page): Assert => $page->has('sources', 2));
    post(route('clipping.preview', $connection), ['external_id' => 'archived'])->assertSessionHasNoErrors();
    $preview = ClippingImport::query()->sole();
    post(route('clipping.import', [$connection, $preview]))->assertRedirect();
    patch(route('clipping.scope.update', $connection), [...$scope, 'import_archives' => false])->assertRedirect();
    get(route('clipping.sources', $connection))->assertInertia(fn(Assert $page): Assert => $page->has('sources', 1));
    $source = OpusSource::query()->sole();
    $before = $source->last_synced_at;
    RefreshClippingSource::dispatchSync($connection->id, $source->id, 'archived');

    expect($source->refresh()->last_synced_at?->toISOString())->toBe($before?->toISOString());
    assertDatabaseCount('opus_clips', 1);
    Http::assertNothingSent();
});
