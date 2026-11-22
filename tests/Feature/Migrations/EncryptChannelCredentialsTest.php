<?php

declare(strict_types=1);

use App\Models\Channel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

test('шифрование прежнего JSON сохраняет поля и допускает откат', function (): void {
    $channel = Channel::factory()->create();
    $tokens = ['access_token' => 'fake-historical-access', 'refresh_token' => 'fake-historical-refresh', 'channel_id' => 'old-account', 'scope' => 'video.list'];
    DB::table('channels')->where('id', $channel->id)->update(['credentials' => json_encode($tokens, JSON_THROW_ON_ERROR)]);
    $migration = require database_path('migrations/2026_11_22_141034_encrypt_stored_channel_credentials.php');

    if (! $migration instanceof Migration || ! method_exists($migration, 'up') || ! method_exists($migration, 'down')) {
        throw new RuntimeException('Invalid credentials migration.');
    }

    $migration->up();
    $migration->up();

    expect($channel->refresh()->credentials)->toMatchArray($tokens)
        ->and($channel->getRawOriginal('credentials'))->not->toContain('fake-historical');
    $migration->down();
    $stored = $channel->refresh()->getRawOriginal('credentials');
    assert(is_string($stored));
    expect(json_decode($stored, true))->toMatchArray($tokens);
});
