<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OpusSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpusSource extends Model
{
    /** @use HasFactory<OpusSourceFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
