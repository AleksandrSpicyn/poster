<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PromptFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Хранит состояние одной генерации: идею, внешние task IDs и URL исходников.
 * Итоговый ролик хранится в Media Library; generated означает завершённую сборку.
 *
 * @property int $id
 * @property string $prompt
 * @property string|null $image_url
 * @property string|null $audio_id
 * @property string|null $audio_url
 * @property string|null $video_id
 * @property string|null $video_url
 * @property bool $generated
 * @property int $project_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, Post> $posts
 * @property-read int|null $posts_count
 * @property-read Project|null $project
 */
class Prompt extends Model implements HasMedia
{
    /** @use HasFactory<PromptFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $casts = ['generated' => 'boolean'];

    protected $fillable = [
        'prompt',
        'image_url',
        'audio_id',
        'audio_url',
        'video_id',
        'video_url',
        'generated',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
