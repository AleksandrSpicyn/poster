<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPrompt
 */
class Prompt extends Model
{
    protected $fillable = [
        'prompt',
        'image_url',
        'audio_url',
        'video_url',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
