<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Models\EpisodeAssembly;
use App\Repositories\EpisodeAssemblyRepository;

readonly class FindEpisodeAssembly
{
    public function __construct(private EpisodeAssemblyRepository $assemblies) {}

    public function run(int $id): ?EpisodeAssembly
    {
        return $this->assemblies->find($id);
    }
}
