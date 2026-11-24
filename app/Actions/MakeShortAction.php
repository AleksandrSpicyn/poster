<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GenerationAccountingMode;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Prompt;
use App\Repositories\GenerationReservationRepository;
use App\Tasks\Production\CreateProductionRun;
use Illuminate\Validation\ValidationException;

readonly class MakeShortAction
{
    public function __construct(private MakeShortSubAction $makeShort, private GenerationReservationRepository $usage, private CreateProductionRun $create) {}

    public function run(Project $project, ?string $requestKey = null): Prompt
    {
        if ($project->owner_id !== null && $this->usage->mode($project->owner_id) === GenerationAccountingMode::ENFORCED->value) {
            $owner = $project->owner;

            if ($requestKey === null || trim($requestKey) === '' || $owner === null) {
                throw ValidationException::withMessages(['request_key' => 'Для платного запуска нужен постоянный ключ запроса.']);
            }

            $run = $this->create->run($project, null, $owner, $requestKey);

            if (! $run instanceof ProductionRun) {
                throw ValidationException::withMessages(['generation' => 'Проект недоступен для запуска.']);
            }

            return $run->prompt ?? $this->makeShort->run($project, $run);
        }

        return $this->makeShort->run($project);
    }
}
