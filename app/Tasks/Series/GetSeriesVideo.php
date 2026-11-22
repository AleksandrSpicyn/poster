<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Models\SeriesAssembly;
use App\Repositories\SeriesAssemblyRepository;
use Illuminate\Validation\ValidationException;

readonly class GetSeriesVideo
{
    public function __construct(private SeriesAssemblyRepository $assemblies) {}

    public function run(int $id, bool $checkSources = true): SeriesAssembly
    {
        $assembly = $this->assemblies->withContext($id, includeParts: ! $checkSources);

        if (! $checkSources) {
            return $assembly;
        }

        if (! $this->assemblies->isCurrent($assembly)) {
            throw ValidationException::withMessages(['assembly' => 'The cycle plan or accepted sources changed. Review the dependent parts.']);
        }

        return $assembly;
    }
}
