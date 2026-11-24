<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;

class HorizonBatchRepository extends DatabaseBatchRepository
{
    public function __construct(BatchFactory $factory, DatabaseManager $database)
    {
        parent::__construct($factory, $database->connection(config()->string('queue.batching.database')), config()->string('queue.batching.table'));
    }

    /** @return array<int, Batch> */
    public function search(string $term, ?string $before): array
    {
        $pattern = '%' . strtr($term, [
            '!' => '!!',
            '%' => '!%',
            '_' => '!_',
        ]) . '%';

        return $this->connection->table($this->table)
            ->whereRaw("(name LIKE ? ESCAPE '!' OR id LIKE ? ESCAPE '!')", [$pattern, $pattern])
            ->when($before !== null, fn(Builder $query) => $query->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn(object $row): Batch => $this->toBatch($row))
            ->values()
            ->all();
    }
}
