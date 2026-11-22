<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int|null $report_project_id
 * @property int|null $channel_id
 * @property int|null $post_id
 * @property int|null $opus_clip_id
 * @property string $platform
 * @property string $account
 * @property string $media_id
 * @property string $observed_on
 * @property string $title
 * @property-read list<array{url?: string|null}>|null $clip_publications
 * @property-read string|null $post_url
 * @property array<string, int|float> $metrics
 */
final class PublicationObservation extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['metrics' => 'array'];
}
