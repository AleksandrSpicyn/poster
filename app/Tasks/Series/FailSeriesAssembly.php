<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Enums\AssemblyStatus;
use App\Models\SeriesAssembly;
use App\Repositories\SeriesAssemblyRepository;

readonly class FailSeriesAssembly
{
    public function __construct(private SeriesAssemblyRepository $assemblies) {}

    public function run(SeriesAssembly $assembly): void
    {
        $current = $this->assemblies->findOrFail($assembly->id);

        if (in_array($current->status, [AssemblyStatus::QUEUED, AssemblyStatus::BUILDING], true)) {
            $this->assemblies->update($current, ['status' => AssemblyStatus::FAILED, 'error_message' => 'Final render failed. Inspect the sources before rebuilding.']);
        }
    }
}
