<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Billing\PilotShotQuote;
use App\Classes\Billing\ProductionQuoteCalculator;
use App\Data\Billing\GenerationQuoteData;
use App\Enums\BillingProduct;
use App\Enums\GenerationAccountingMode;
use App\Enums\ProjectContentMode;
use App\Enums\RevisionStatus;
use App\Models\User;
use App\Repositories\BillingAccountingRepository;
use App\Repositories\BillingWorkspaceRepository;
use App\Repositories\ProductionBudgetRepository;
use App\Tasks\Shots\PrepareShotVideoSettings;
use Illuminate\Validation\ValidationException;

final readonly class QuoteGenerationAction
{
    public function __construct(
        private BillingAccountingRepository $accounting,
        private ProductionBudgetRepository $budgets,
        private ProductionQuoteCalculator $quotes,
        private BillingWorkspaceRepository $workspace,
        private PrepareShotVideoSettings $settings,
    ) {}

    /**
     * @return array{seconds: ?int, shots: array<int, int>, video: array<string, mixed>, revision_id: ?int, enforced: bool,
     *     required_seconds: ?int, available_seconds: int, can_start: bool, shortfall_seconds: ?int, available_after_seconds: int, catalog_version: string}
     */
    public function run(User $owner, GenerationQuoteData $data): array
    {
        if ($data->shot_id !== null) {
            $shot = $this->budgets->quoteShot($owner->id, $data);

            if ($data->revision_id !== null && $shot->scene?->episode_revision_id !== $data->revision_id) {
                throw ValidationException::withMessages(['revision_id' => 'Версия сценария изменилась. Обновите страницу.']);
            }

            $video = $this->settings->run($shot)->videoSnapshot();
            $quote = [
                'seconds' => PilotShotQuote::seconds($video),
                'shots' => [$shot->id => intdiv($shot->duration_ms, 1000)],
                'video' => $video,
                'revision_id' => $shot->scene?->episode_revision_id,
            ];
        } else {
            $quote = $this->quoteProject($owner->id, $data);
        }

        $balance = $this->workspace->balance($owner->id);
        $required = $quote['seconds'];
        $canStart = $required !== null && $balance->available >= $required;

        return [
            ...$quote,
            'enforced' => $this->accounting->storedMode($owner->id) === GenerationAccountingMode::ENFORCED->value,
            'required_seconds' => $required,
            'available_seconds' => $balance->available,
            'can_start' => $canStart,
            'shortfall_seconds' => $required === null ? null : max(0, $required - $balance->available),
            'available_after_seconds' => $canStart ? $balance->available - $required : $balance->available,
            'catalog_version' => BillingProduct::CATALOG_VERSION,
        ];
    }

    /** @return array{seconds: int, shots: array<int, int>, video: array<string, mixed>, revision_id: ?int} */
    private function quoteProject(int $owner, GenerationQuoteData $data): array
    {
        $project = $this->budgets->ownedProject($owner, $data->project_id);

        if ($project->isClippingProject() || ($project->content_mode === ProjectContentMode::SERIES && $data->series_id === null)) {
            throw ValidationException::withMessages(['series_id' => 'Выберите сериал для расчёта.']);
        }

        $series = $data->series_id === null ? null : $this->budgets->projectSeries($project, $data->series_id);
        $revision = $this->budgets->latestUnacceptedRevision($series);
        $revision = $revision?->status === RevisionStatus::APPROVED ? $revision : null;

        if ($data->revision_id !== null && $revision?->id !== $data->revision_id) {
            throw ValidationException::withMessages(['revision_id' => 'Версия сценария изменилась. Обновите страницу.']);
        }

        $quote = $this->quotes->calculate($series, $revision);

        return [...$quote->reservationSnapshot(), 'revision_id' => $quote->revisionId];
    }
}
