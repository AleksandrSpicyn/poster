<?php

declare(strict_types=1);

use App\Enums\AssemblyStatus;
use App\Jobs\BuildEpisodeAssemblyJob;
use App\Models\EpisodeAssembly;
use Illuminate\Support\Facades\Queue;

use function Tests\Support\consoleCommand;

test('планировщик передаёт лимит восстановления монтажей', function (): void {
    Queue::fake();
    EpisodeAssembly::factory()->count(2)->create([
        'status' => AssemblyStatus::QUEUED,
        'updated_at' => now()->subMinutes(2),
    ]);

    consoleCommand('app:reconcile-episode-assemblies', ['--limit' => 1])
        ->expectsOutput('Requeued episode assemblies: 1')
        ->assertSuccessful();

    Queue::assertPushed(BuildEpisodeAssemblyJob::class, 1);
});
