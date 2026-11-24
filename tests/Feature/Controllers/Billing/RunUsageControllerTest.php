<?php

declare(strict_types=1);

use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Tests\Support\billingOrder;
use function Tests\Support\billingOwner;

test('запуск показывает собственные фактические резервы по источникам секунд', function (): void {
    $owner = billingOwner();
    $project = Project::factory()->for($owner, 'owner')->create();
    $run = ProductionRun::factory()->for($project)->create();
    $order = billingOrder($owner);
    $grant = DB::table('generation_grants')->insertGetId(['owner_id' => $owner->id, 'order_id' => $order, 'entitlement_key' => 'monthly',
        'starts_at' => now('UTC'), 'granted_seconds' => 120, 'available_seconds' => 112, 'held_seconds' => 8]);
    $reservation = DB::table('generation_reservations')->insertGetId(['owner_id' => $owner->id, 'intent_key' => 'short', 'request_hash' => str_repeat('a', 64),
        'accounting_mode' => 'enforced', 'status' => 'held', 'production_run_id' => $run->id, 'quote_snapshot' => '{}']);
    $part = DB::table('generation_reservation_parts')->insertGetId(['owner_id' => $owner->id, 'reservation_id' => $reservation, 'grant_id' => $grant,
        'take_key' => 'take-1', 'seconds' => 8, 'status' => 'held']);
    DB::table('generation_credit_entries')->insert(['owner_id' => $owner->id, 'grant_id' => $grant, 'part_id' => $part,
        'event_key' => 'hold.1', 'kind' => 'hold', 'held_delta' => 8, 'available_delta' => -8, 'created_at' => now('UTC')]);
    $url = route('billing.usage.run', $run);

    actingAs(User::factory()->create())->getJson($url)->assertNotFound();
    $response = actingAs($owner)->getJson($url)->assertOk()->assertJsonPath('sources.0.product_label', 'Автор');
    expect($response->json('sources.0.held'))->toEqual(8);
});
