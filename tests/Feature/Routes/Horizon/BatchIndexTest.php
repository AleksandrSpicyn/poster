<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Tests\Support\serialOperator;

test('ищет пакеты задач одним запросом', function (int $size): void {
    $actor = serialOperator();
    $rows = collect(range(1, $size))->map(static fn(int $index): array => [
        'id' => sprintf('%05d', $index), 'name' => 'audit match', 'total_jobs' => 1, 'pending_jobs' => 0,
        'failed_jobs' => 0, 'failed_job_ids' => '[]', 'options' => serialize([]), 'created_at' => now()->timestamp,
    ]);

    foreach ($rows->chunk(100) as $chunk) {
        DB::table('job_batches')->insert($chunk->all());
    }

    DB::enableQueryLog();

    try {
        actingAs($actor)
            ->getJson(route('horizon.jobs-batches.index', ['query' => 'match', 'before_id' => sprintf('%05d', $size + 1)]))
            ->assertOk()
            ->assertJsonCount(min(50, $size), 'batches')
            ->assertJsonPath('batches.0.id', sprintf('%05d', $size));
        $reads = collect(DB::getQueryLog())->pluck('query')->filter(fn(mixed $sql): bool => is_string($sql) && str_contains($sql, 'job_batches'));
    } finally {
        DB::disableQueryLog();
    }

    expect($reads)->toHaveCount(1);
})->with([1, 10, 100, 1000]);
