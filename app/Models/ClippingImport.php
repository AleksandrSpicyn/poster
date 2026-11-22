<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $clipping_connection_id
 * @property int|null $project_id
 * @property string $external_id
 * @property string|null $source_name
 * @property string|null $game
 * @property list<array{id: string, platform: string, external_id: string, name: string, channel_id: int|null}>|null $accounts
 * @property int $clip_count
 * @property Carbon|null $imported_at
 * @property list<array{id: string, title: string|null, durationMs: int|null, score: float|null, publications: list<array{platform: string, status: string|null, url: string|null, publish_at: string|null}>}> $clips
 */
class ClippingImport extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['clips'];

    protected $casts = ['clips' => 'array', 'accounts' => 'array', 'imported_at' => 'datetime'];
}
