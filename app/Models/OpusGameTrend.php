<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property list<array{title: string, channel: string, views: string, views_per_day?: int|null, url: string}> $entries */
class OpusGameTrend extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['entries' => 'array'];
}
