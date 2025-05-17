<?php

namespace App\Models;

use App\Classes\Publishers\PublisherFactory;
use App\Enums\ChannelType;
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

    protected $casts = [
        'type' => ChannelType::class,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function publish(string $url): bool
    {
        return PublisherFactory::make($this->type)->publish($url);
    }
}
