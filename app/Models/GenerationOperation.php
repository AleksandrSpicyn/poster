<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\VideoProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $prompt_id
 * @property GenerationOperationStage $stage
 * @property GenerationOperationStatus $status
 * @property string $request_key
 * @property string $idempotency_key
 * @property VideoProvider|null $provider
 * @property string|null $model
 * @property array<string, mixed> $request_snapshot
 * @property array<string, mixed>|null $result_snapshot
 * @property string|null $external_id
 * @property string|null $error_code
 * @property int $check_count
 * @property Carbon|null $next_check_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancel_requested_at
 * @property Carbon $updated_at
 * @property-read Prompt|null $prompt
 */
class GenerationOperation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'stage' => GenerationOperationStage::class,
        'status' => GenerationOperationStatus::class,
        'provider' => VideoProvider::class,
        'request_snapshot' => 'array',
        'result_snapshot' => 'array',
        'check_count' => 'integer',
        'next_check_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancel_requested_at' => 'datetime',
    ];

    /** @return BelongsTo<Prompt, $this> */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }
}
