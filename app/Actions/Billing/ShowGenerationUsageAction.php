<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Generators\VideoGenerator\Seedance\Enums\Model;
use App\Classes\Generators\VideoGenerator\Seedance\Enums\Resolution;
use App\Enums\VideoProvider;
use App\Models\ProductionRun;
use App\Models\Shot;
use App\Models\User;
use App\Repositories\BillingWorkspaceRepository;
use App\Tasks\Shots\PrepareShotVideoSettings;
use Illuminate\Validation\ValidationException;

final readonly class ShowGenerationUsageAction
{
    public function __construct(private BillingWorkspaceRepository $workspace, private PrepareShotVideoSettings $settings) {}

    /** @return array<string, mixed> */
    public function shot(User $owner, Shot $shot): array
    {
        $estimate = null;

        try {
            $settings = $this->settings->run($shot);

            if ($settings->provider === VideoProvider::SEEDANCE && $settings->capabilities->model === Model::V2_0_MINI->value
                && $settings->capabilities->defaultResolution === Resolution::HD->value && $settings->generateAudio) {
                $estimate = $settings->duration;
            }
        } catch (ValidationException) {
            // Incompatible historical settings have no pilot estimate.
        }

        return ['estimate' => $estimate, 'available' => $this->workspace->balance($owner->id)->available,
            'sources' => $this->workspace->usage($owner->id, $shot->id, null)];
    }

    /** @return array<string, mixed> */
    public function run(User $owner, ProductionRun $run): array
    {
        return ['estimate' => null, 'available' => $this->workspace->balance($owner->id)->available,
            'sources' => $this->workspace->usage($owner->id, null, $run->id)];
    }
}
