<?php

declare(strict_types=1);

use App\Enums\ProjectContentMode;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Tests\Support\billingOrder;
use function Tests\Support\episodeHttpFixture;
use function Tests\Support\serialReferenceMedia;

test('котировка и групповой резерв учитывают только недостающие кадры версии', function (): void {
    Storage::fake('local');
    Bus::fake();
    $fixture = episodeHttpFixture();
    $owner = $fixture['user'];
    $series = $fixture['episode']->series()->firstOrFail();
    $project = $series->project()->firstOrFail();
    $project->update(['content_mode' => ProjectContentMode::SERIES]);

    $shot = $fixture['shot'];
    serialReferenceMedia($fixture['revision']->characterRevisions()->firstOrFail());
    $ready = $shot->replicate();
    $ready->position = 2;
    $ready->save();
    Prompt::factory()->for($project)->create(['shot_id' => $ready->id, 'generated' => true, 'take_number' => 1]);
    DB::table('billing_accounts')->insert(['owner_id' => $owner->id, 'accounting_mode' => 'enforced']);
    DB::table('generation_grants')->insert(['owner_id' => $owner->id, 'order_id' => billingOrder($owner), 'entitlement_key' => 'month', 'starts_at' => now()->subDay(),
        'granted_seconds' => 120, 'available_seconds' => 120]);
    $data = ['project_id' => $project->id, 'series_id' => $series->id, 'revision_id' => $fixture['revision']->id];
    $url = route('billing.quotes.store');
    $seconds = intdiv($shot->duration_ms, 1000);

    actingAs($owner)->postJson($url, [...$data, 'revision_id' => $fixture['revision']->id + 1])->assertUnprocessable();
    actingAs($owner)->postJson($url, $data)->assertOk()->assertJsonPath('required_seconds', $seconds)->assertJsonPath('available_after_seconds', 120 - $seconds);
    assertDatabaseCount('generation_reservation_parts', 0);
    actingAs($owner)->post(route('production_runs.store', $project), ['request_key' => 'episode-budget', 'series_id' => $series->id, 'channel_ids' => []])->assertSessionHasNoErrors();
    assertDatabaseHas('generation_grants', ['available_seconds' => 120 - $seconds, 'held_seconds' => $seconds]);
    actingAs($owner)->post(route('shots.generate', $fixture['params']), ['request_key' => 'manual-in-episode'])->assertSessionHasNoErrors();
    $take = $shot->prompts()->sole();
    assertDatabaseHas('generation_reservation_parts', ['prompt_id' => $take->id, 'shot_id' => $shot->id, 'seconds' => $seconds]);
    assertDatabaseHas('generation_grants', ['available_seconds' => 120 - $seconds, 'held_seconds' => $seconds]);
    Http::assertNothingSent();
});

test('чужой проект недоступен для расчёта', function (): void {
    $fixture = episodeHttpFixture();
    $other = User::factory()->create();
    actingAs($other)->postJson(route('billing.quotes.store'), ['project_id' => $fixture['episode']->series()->firstOrFail()->project_id, 'shot_id' => $fixture['shot']->id])->assertNotFound();
});
