<?php

declare(strict_types=1);

use App\Enums\BillingProduct;
use App\Events\WorkspaceUpdated;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Tests\Support\billingOrder;
use function Tests\Support\consoleCommand;
use function Tests\Support\episodeHttpFixture;
use function Tests\Support\serialReferenceMedia;

test('платный кадр резервирует два пакета и возвращает истёкшие секунды в исходный месяц', function (): void {
    Storage::fake('local');
    Bus::fake();
    $context = episodeHttpFixture();
    serialReferenceMedia($context['revision']->characterRevisions()->firstOrFail());
    $owner = $context['user'];
    DB::table('billing_accounts')->insert(['owner_id' => $owner->id, 'accounting_mode' => 'enforced']);
    $seconds = intdiv($context['shot']->duration_ms, 1000);
    $url = route('shots.generate', $context['params']);
    $grant = ['owner_id' => $owner->id, 'starts_at' => now('UTC')->subDay(), 'granted_seconds' => 2, 'available_seconds' => 2];
    $perpetual = billingOrder($owner, BillingProduct::ONE_OFF);
    $month = billingOrder($owner);
    DB::table('generation_grants')->insert([...$grant, 'order_id' => $month, 'entitlement_key' => 'month', 'ends_at' => now('UTC')->addDay()]);

    Event::fake([WorkspaceUpdated::class]);
    actingAs($owner)->post($url, ['request_key' => 'mixed'])->assertSessionHasErrors('balance');
    assertDatabaseCount('generation_reservations', 0);
    Bus::assertNothingDispatched();
    Event::assertNotDispatched(WorkspaceUpdated::class, fn(WorkspaceUpdated $event): bool => $event->broadcastWith()['resource'] === 'GenerationBalance');
    DB::table('generation_grants')->insert([...$grant, 'order_id' => $perpetual, 'entitlement_key' => 'trial', 'available_seconds' => 30, 'granted_seconds' => 30]);
    actingAs($owner)->post($url, ['request_key' => 'mixed'])->assertSessionHasNoErrors();
    actingAs($owner)->post($url, ['request_key' => 'mixed'])->assertSessionHasNoErrors();
    $take = $context['shot']->prompts()->sole();
    expect(data_get($take->input_snapshot, 'video.aspect_ratio'))->toBe('adaptive');
    assertDatabaseCount('generation_reservation_parts', 2);
    Event::assertDispatched(WorkspaceUpdated::class, fn(WorkspaceUpdated $event): bool => $event->broadcastWith()['resource'] === 'GenerationBalance'
        && $event->broadcastOn()[0]->name === 'private-workspace.' . $owner->id);
    assertDatabaseHas('generation_grants', ['order_id' => $month, 'held_seconds' => 2, 'available_seconds' => 0]);
    assertDatabaseHas('generation_grants', ['order_id' => $perpetual, 'held_seconds' => $seconds - 2]);

    DB::table('generation_grants')->where('order_id', $month)->update(['ends_at' => now('UTC')->subMinute()]);
    consoleCommand('billing:process-periods')->assertSuccessful();
    actingAs($owner)->post(route('shots.cancel', [...$context['params'], 'prompt' => $take->id]))->assertSessionHasNoErrors();

    assertDatabaseHas('generation_grants', ['order_id' => $month, 'expired_seconds' => 2, 'held_seconds' => 0]);
    assertDatabaseHas('generation_grants', ['order_id' => $perpetual, 'available_seconds' => 30, 'held_seconds' => 0]);
    assertDatabaseCount('generation_credit_entries', 4);
    Http::assertNothingSent();
});
