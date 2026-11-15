<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Группирует исходную тему, историю генераций и каналы доставки роликов.
 * В текущей схеме не привязан к User: кабинет показывает общие данные приложения.
 *
 * @property int $id
 * @property string $name
 * @property string $prompt
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Channel> $channels
 * @property-read int|null $channels_count
 * @property-read Collection<int, Prompt> $prompts
 * @property-read int|null $prompts_count
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'prompt',
    ];

    /** @return HasMany<Prompt, $this> */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    /** @return HasMany<Channel, $this> */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }
}
