<?php

declare(strict_types=1);

namespace App\Http\Controllers\Horizon;

use App\Repositories\HorizonBatchRepository;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Laravel\Horizon\Http\Controllers\BatchesController as HorizonController;

class BatchesController extends HorizonController
{
    public function __construct(BatchRepository $batches, private readonly HorizonBatchRepository $search)
    {
        parent::__construct($batches);
    }

    /** @return array{batches: array<array-key, Batch>} */
    public function index(Request $request): array
    {
        $before = $request->filled('before_id') ? (string) $request->string('before_id') : null;

        try {
            return ['batches' => $request->query('query')
                ? $this->search->search((string) $request->string('query'), $before)
                : $this->batches->get(50, $before)];
        } catch (QueryException) {
            return ['batches' => []];
        }
    }
}
