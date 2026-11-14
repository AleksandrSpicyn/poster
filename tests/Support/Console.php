<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;

/** @param array<string, int|string> $arguments */
function consoleCommand(string $command, array $arguments = []): PendingCommand
{
    $pending = artisan($command, $arguments);
    assert($pending instanceof PendingCommand);

    return $pending;
}
