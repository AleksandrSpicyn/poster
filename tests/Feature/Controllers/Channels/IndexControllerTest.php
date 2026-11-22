<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('оператор видит общие каналы без секретов', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['client_secret' => 'never-render-secret', 'credentials' => ['access_token' => 'never-render-token', 'account_name' => 'Wildlife']]);
    $other = Project::factory()->recycle(workspaceOwner())->create();
    $channel->projects()->attach($other);

    $response = actingAs(serialOperator())->get(route('channels.index'));

    $response->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->component('Channels/Index')
            ->has('channels.data', 1)->where('channels.data.0.id', $channel->id)
            ->where('channels.data.0.account_name', 'Wildlife')->where('channels.data.0.connected', true)
            ->has('channels.data.0.projects', 2)->missing('channels.data.0.credentials')->missing('channels.data.0.client_secret'));

    expect($response->getContent())->not->toContain('never-render-secret', 'never-render-token');
});

test('подтверждённому пользователю доступны свои каналы', function (): void {
    actingAs(User::factory()->create())->get(route('channels.index'))->assertOk();

    Http::assertNothingSent();
});

test('гость и неподтверждённый пользователь не открывают канал', function (): void {
    get(route('channels.index'))->assertRedirect(route('login'));

    actingAs(User::factory()->unverified()->create())->get(route('channels.index'))->assertRedirect(route('verification.notice'));
});
