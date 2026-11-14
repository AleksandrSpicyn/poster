<?php

declare(strict_types=1);

use App\Models\Prompt;

use function Pest\Laravel\assertDatabaseCount;
use function Tests\Support\consoleCommand;

test('команда сборки пропускает идеи без исходных файлов', function (): void {
    $prompt = Prompt::factory()->create();

    consoleCommand('app:save-video')->assertSuccessful();

    expect($prompt->refresh()->generated)->toBeFalse();

    assertDatabaseCount('media', 0);
});
