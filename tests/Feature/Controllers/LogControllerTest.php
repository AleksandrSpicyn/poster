<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;

use function Pest\Laravel\postJson;

test('UKassa подтверждает callback и журналирует полученные данные', function (): void {
    $log = Log::spy();

    postJson(route('webhook.ukassa'), ['event' => 'payment.succeeded'])
        ->assertOk()
        ->assertContent('');
    $log
        ->shouldHaveReceived('info')
        ->once()
        ->with('Webhook UKassa received', ['event' => 'payment.succeeded']);
});
