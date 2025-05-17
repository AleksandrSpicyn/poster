<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperChannel
 */
class Channel extends Model
{
    protected $fillable = [
        'name',
        'type',
        'api_key',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
