<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetricCollectionStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property MetricCollectionStatus $status
 * @property string $name
 */
final class ChannelMetricSync extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['status' => MetricCollectionStatus::class, 'last_success_at' => 'datetime'];
}
