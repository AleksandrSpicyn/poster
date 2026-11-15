<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectContentMode;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Группирует исходную тему, историю генераций и каналы доставки роликов.
 * В текущей схеме не привязан к User: кабинет показывает общие данные приложения.
 *
 * @property int $id
 * @property string $name
 * @property string $prompt
 * @property ProjectContentMode $content_mode
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
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'prompt',
        'content_mode',
    ];

    protected $casts = ['content_mode' => ProjectContentMode::class];

    protected $attributes = ['content_mode' => ProjectContentMode::SHORT->value];

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

    /** @return HasMany<Channel, $this> */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
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
