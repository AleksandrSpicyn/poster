<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Tests\Support\billingOrder;
use function Tests\Support\consoleCommand;
use function Tests\Support\episodeHttpFixture;
use function Tests\Support\serialReferenceMedia;

test('владелец наблюдается только после включения, повтор запроса сохраняет одну котировку', function (): void {
    Storage::fake('local');
    $context = episodeHttpFixture();
    serialReferenceMedia($context['revision']->characterRevisions()->firstOrFail());
    Bus::fake();
    $owner = $context['user'];
    $url = route('shots.generate', $context['params']);

    consoleCommand('billing:shadow-usage', ['owner' => $owner->id])->expectsOutput('Режим учёта: legacy')->assertSuccessful();
    assertDatabaseCount('billing_accounts', 0);
    consoleCommand('billing:shadow-usage', ['owner' => $owner->id, '--enable' => true])->assertSuccessful();
    $order = billingOrder($owner);
    DB::table('generation_grants')->insert(['owner_id' => $owner->id, 'order_id' => $order, 'entitlement_key' => 'purchased',
        'starts_at' => now('UTC')->subDay(), 'granted_seconds' => 1, 'available_seconds' => 1]);
    actingAs($owner)->post($url, ['request_key' => 'shadow-replay'])->assertSessionHasNoErrors();
    actingAs($owner)->post($url, ['request_key' => 'shadow-replay'])->assertSessionHasNoErrors();
    $take = $context['shot']->prompts()->sole();
    $seconds = intdiv($context['shot']->duration_ms, 1000);

    assertDatabaseCount('generation_reservations', 1);
    assertDatabaseHas('generation_reservations', ['prompt_id' => $take->id, 'status' => 'held', 'owner_id' => $owner->id,
        'accounting_mode' => 'shadow', 'request_hash' => $take->input_hash, 'episode_revision_id' => $context['revision']->id, 'quote_snapshot->seconds' => $seconds]);
    consoleCommand('billing:shadow-usage', ['owner' => $owner->id])
        ->expectsOutput('Режим учёта: shadow')->expectsTable(['Состояние', 'Дубли', 'Расчётные секунды'], [['held', 1, $seconds]])->assertSuccessful();
    consoleCommand('billing:shadow-usage', ['owner' => User::factory()->create()->id])
        ->expectsOutput('Наблюдавшихся дублей пока нет.')->assertSuccessful();
    assertDatabaseHas('generation_grants', ['order_id' => $order, 'available_seconds' => 1, 'held_seconds' => 0, 'consumed_seconds' => 0]);
    actingAs($owner)->get(route('billing.history', ['tab' => 'seconds']))->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->has('history.data', 0));
    assertDatabaseCount('generation_credit_entries', 0);
    Http::assertNothingSent();

    consoleCommand('billing:shadow-usage', ['owner' => $owner->id, '--disable' => true])->assertSuccessful();
    actingAs($owner)->post(route('shots.cancel', [...$context['params'], 'prompt' => $take->id]))->assertSessionHasNoErrors();
    actingAs($owner)->post($url, ['request_key' => 'legacy-take', 'new_take' => true])->assertSessionHasNoErrors();

    expect($context['shot']->prompts()->count())->toBe(2);
    assertDatabaseCount('generation_reservations', 1);
    assertDatabaseHas('generation_reservations', ['prompt_id' => $take->id, 'status' => 'released']);
    assertDatabaseHas('billing_accounts', ['owner_id' => $owner->id, 'sales_enabled' => false, 'automatic_payments_enabled' => false, 'accounting_mode' => 'legacy']);
});

test('оператор не может сменить строгий режим или создать счёт для неизвестного владельца', function (): void {
    $owner = User::factory()->create();
    DB::table('billing_accounts')->insert(['owner_id' => $owner->id, 'accounting_mode' => 'enforced']);

    consoleCommand('billing:shadow-usage', ['owner' => $owner->id, '--disable' => true])->assertFailed();
    consoleCommand('billing:shadow-usage', ['owner' => $owner->id + 1, '--enable' => true])->assertFailed();

    assertDatabaseCount('billing_accounts', 1);
    assertDatabaseHas('billing_accounts', ['owner_id' => $owner->id, 'accounting_mode' => 'enforced']);
});
