<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PromptStage;
use App\Enums\VideoProvider;
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
 * @property VideoProvider|null $video_provider
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

    protected $casts = ['generated' => 'boolean', 'video_provider' => VideoProvider::class];

    protected $fillable = [
        'prompt',
        'image_url',
        'audio_id',
        'audio_url',
        'video_id',
        'video_url',
        'video_provider',
        'generated',
    ];

    /** Готовность определяется результатами; старые задачи без результата остаются ожидающими. */
    public function stage(): PromptStage
    {
        return match (true) {
            $this->generated => PromptStage::READY,
            (bool) $this->video_url && (bool) $this->audio_url => PromptStage::ASSEMBLY,
            (bool) $this->video_url => PromptStage::AUDIO,
            (bool) $this->audio_url => PromptStage::VIDEO,
            (bool) $this->video_id || (bool) $this->audio_id => PromptStage::MEDIA,
            (bool) $this->image_url => PromptStage::STARTING_MEDIA,
            default => PromptStage::IMAGE,
        };
    }

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
