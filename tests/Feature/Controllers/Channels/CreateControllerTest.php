<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\get;
use function Tests\Support\serialOperator;

test('оператор получает форму поддерживаемых платформ', function (): void {
    actingAs(serialOperator())->get(route('channels.create'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->component('Channels/Form')
            ->where('channel', null)->where('channelTypes.youtube', 'Youtube')->where('channelTypes.instagram', 'Instagram'));

    assertDatabaseCount('channels', 0);
    Http::assertNothingSent();
});

test('подтверждённому пользователю доступны свои каналы', function (): void {
    actingAs(User::factory()->create())->get(route('channels.create'))->assertOk();

    Http::assertNothingSent();
});

test('гость и неподтверждённый пользователь не открывают канал', function (): void {
    get(route('channels.create'))->assertRedirect(route('login'));

    actingAs(User::factory()->unverified()->create())->get(route('channels.create'))->assertRedirect(route('verification.notice'));
});
