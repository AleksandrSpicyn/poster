<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Billing\PilotShotQuote;
use App\Data\Billing\GenerationQuoteData;
use App\Enums\BillingProduct;
use App\Enums\GenerationAccountingMode;
use App\Models\ProductionRun;
use App\Models\Shot;
use App\Models\User;
use App\Repositories\BillingWorkspaceRepository;
use App\Repositories\GenerationReservationRepository;
use App\Repositories\ProductionBudgetRepository;
use App\Tasks\Shots\PrepareShotVideoSettings;
use Illuminate\Validation\ValidationException;
use stdClass;

final readonly class ShowGenerationUsageAction
{
    public function __construct(private GenerationReservationRepository $reservations, private ProductionBudgetRepository $budgets, private BillingWorkspaceRepository $workspace, private PrepareShotVideoSettings $settings) {}

    /** @return array<string, mixed> */
    public function quote(User $owner, GenerationQuoteData $data): array
    {
        if ($data->shot_id !== null) {
            $shot = $this->budgets->quoteShot($owner->id, $data);
            $video = $this->settings->run($shot)->videoSnapshot();
            $quote = ['seconds' => PilotShotQuote::seconds($video), 'shots' => [$shot->id => intdiv($shot->duration_ms, 1000)], 'video' => $video,
                'revision_id' => $shot->scene?->episode_revision_id];
        } else {
            $quote = $this->budgets->quoteProject($owner->id, $data);
        }

        /** @var stdClass&object{available: int} $balance */
        $balance = $this->workspace->balance($owner->id);
        $required = $quote['seconds'];
        $canStart = $required !== null && $balance->available >= $required;

        return [...$quote, 'enforced' => $this->reservations->mode($owner->id) === GenerationAccountingMode::ENFORCED->value, 'required_seconds' => $required, 'available_seconds' => $balance->available, 'can_start' => $canStart,
            'shortfall_seconds' => $required === null ? null : max(0, $required - $balance->available),
            'available_after_seconds' => $canStart ? $balance->available - $required : $balance->available, 'catalog_version' => BillingProduct::CATALOG_VERSION];
    }

    /** @return array<string, mixed> */
    public function shot(User $owner, Shot $shot): array
    {
        $estimate = null;

        try {
            $estimate = PilotShotQuote::seconds($this->settings->run($shot)->videoSnapshot());
        } catch (ValidationException) {
            // Incompatible historical settings have no pilot estimate.
        }

        return ['enforced' => $this->reservations->mode($owner->id) === GenerationAccountingMode::ENFORCED->value, 'estimate' => $estimate, 'available' => $this->workspace->balance($owner->id)->available,
            'sources' => $this->workspace->usage($owner->id, $shot->id, null)];
    }

    /** @return array<string, mixed> */
    public function run(User $owner, ProductionRun $run): array
    {
        return ['enforced' => $this->reservations->mode($owner->id) === GenerationAccountingMode::ENFORCED->value, ...$this->workspace->runQuote($owner->id, $run->id), 'available' => $this->workspace->balance($owner->id)->available,
            'sources' => $this->workspace->usage($owner->id, null, $run->id)];
    }
}
