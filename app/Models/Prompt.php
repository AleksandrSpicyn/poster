<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @mixin IdeHelperPrompt
 */
class Prompt extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** @use HasFactory<\Database\Factories\PromptFactory> */
    use HasFactory;

    protected $fillable = [
        'prompt',
        'image_url',
        'audio_id',
        'audio_url',
        'video_id',
        'video_url',
        'generated',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
