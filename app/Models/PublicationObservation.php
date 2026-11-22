<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int|null $post_id
 * @property int|null $opus_clip_id
 * @property string $platform
 * @property string $account
 * @property string $media_id
 * @property string $observed_on
 * @property string $title
 * @property array<string, int|float> $metrics
 */
final class PublicationObservation extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['metrics' => 'array'];
}
