<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Локальная запись результата внешней публикации с её ID и URL.
 * Связи nullable: история сохраняется при удалении канала или Prompt.
 *
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string|null $external_id
 * @property string|null $url
 * @property int|null $channel_id
 * @property int|null $prompt_id
 * @property int|null $episode_assembly_id
 * @property int|null $publication_request_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Channel|null $channel
 * @property-read Prompt|null $prompt
 * @property-read EpisodeAssembly|null $episodeAssembly
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'external_id',
        'prompt_id',
        'episode_assembly_id', 'series_assembly_id',
        'publication_request_id',
        'url',
    ];

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<EpisodeAssembly, $this> */
    public function episodeAssembly(): BelongsTo
    {
        return $this->belongsTo(EpisodeAssembly::class);
    }

    /** @return BelongsTo<SeriesAssembly, $this> */
    public function seriesAssembly(): BelongsTo
    {
        return $this->belongsTo(SeriesAssembly::class);
    }

    /** @return BelongsTo<Prompt, $this> */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }
}
