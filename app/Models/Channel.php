<?php

namespace App\Models;

use App\Classes\Publishers\PublisherFactory;
use App\Enums\ChannelType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperChannel
 */
class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'client_id',
        'client_secret',
        'credentials',
    ];

    protected $casts = [
        'type' => ChannelType::class,
        'credentials' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function publish(string $url): bool
    {
        return PublisherFactory::make($this)->publish($url);
    }
}
