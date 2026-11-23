<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $provider
 * @property string $external_id
 * @property int|null $prompt_id
 * @property int|null $operation_id
 * @property string|null $requested_model
 * @property string|null $result_model
 * @property string|null $input_mode
 * @property string|null $resolution
 * @property string $status
 * @property int|null $duration
 * @property int|null $completion_tokens
 * @property int|null $charge_e8
 * @property string|null $currency
 * @property string|null $finance_reference
 * @property CarbonImmutable|null $reconciled_at
 */
class GenerationProviderUsage extends Model
{
    public $timestamps = false;

    protected $table = 'generation_provider_usage';

    protected $casts = ['charge_e8' => 'integer', 'completion_tokens' => 'integer', 'reconciled_at' => 'immutable_datetime'];
}
