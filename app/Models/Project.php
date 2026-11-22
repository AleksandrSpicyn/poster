<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectContentMode;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Группирует исходную тему, историю генераций и каналы доставки роликов.
 *
 * @property-read User|null $owner
 * @property-read Collection<int, Series> $automatedSeries
 * @property int|null $owner_id
 * @property int $settings_version
 * @property int $id
 * @property int|null $opus_source_id
 * @property int|null $clipping_connection_id
 * @property string|null $clipping_workspace
 * @property string $name
 * @property string $prompt
 * @property ProjectContentMode $content_mode
 * @property Carbon|null $archived_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Channel> $channels
 * @property-read Collection<int, Series> $series
 * @property-read Series|null $seriesDefinition
 * @property-read int|null $channels_count
 * @property-read Collection<int, Prompt> $prompts
 * @property-read Collection<int, ProductionRun> $productionRuns
 * @property-read ProductionRun|null $latestProductionRun
 * @property-read int|null $prompts_count
 * @property-read int|null $ready_prompts_count
 */
class Project extends Model implements HasMedia
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;
    use InteractsWithMedia;

    public const string COVER = 'project-cover';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COVER)->useDisk('local')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /** @return HasOneThrough<Media, Prompt, $this> */
    public function coverFrame(): HasOneThrough
    {
        return $this->hasOneThrough(Media::class, Prompt::class, 'project_id', 'model_id')
            ->ofMany(['id' => 'max'], static function (Builder $query): void {
                $query->where('media.model_type', new Prompt()->getMorphClass())->where('media.collection_name', Prompt::SHOT_IMAGE);
            });
    }

    public function coverImage(): ?Media
    {
        return $this->getMedia(self::COVER)->sortByDesc('id')->first() ?? $this->coverFrame;
    }

    public const string OPUS_CLIP_WORKSPACE = 'opusclip';

    public function isClippingProject(): bool
    {
        return $this->clipping_connection_id !== null || $this->clipping_workspace !== null || $this->opus_source_id !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<Series, $this> */
    public function automatedSeries(): HasMany
    {
        return $this->series()->whereNull('archived_at')->where('automation_enabled', true)->orderBy('id');
    }

    /** @return HasMany<OpusSource, $this> */
    public function opusSources(): HasMany
    {
        return $this->hasMany(OpusSource::class);
    }

    protected $fillable = [
        'clipping_workspace',
        'name',
        'prompt',
        'content_mode',
        'is_active',
    ];

    protected $casts = ['content_mode' => ProjectContentMode::class, 'is_active' => 'boolean', 'archived_at' => 'datetime'];

    protected $attributes = ['settings_version' => 1, 'content_mode' => ProjectContentMode::SHORT->value, 'is_active' => true];

    /** @return HasMany<ProductionRun, $this> */
    public function productionRuns(): HasMany
    {
        return $this->hasMany(ProductionRun::class);
    }

    /** @return HasOne<ProductionRun, $this> */
    public function latestProductionRun(): HasOne
    {
        return $this->hasOne(ProductionRun::class)->latestOfMany();
    }

    /** @return HasMany<Prompt, $this> */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    /** @return HasMany<Prompt, $this> */
    public function readyPrompts(): HasMany
    {
        return $this->prompts()->where('generated', true);
    }

    /** @return BelongsToMany<Channel, $this> */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class);
    }

    /** @return HasMany<Series, $this> */
    public function series(): HasMany
    {
        return $this->hasMany(Series::class);
    }

    /** @return HasOne<Series, $this> */
    public function seriesDefinition(): HasOne
    {
        return $this->hasOne(Series::class)->oldestOfMany();
    }
}
