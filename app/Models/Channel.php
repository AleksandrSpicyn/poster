<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChannelType;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Общий канал публикации: тип выбирает адаптер, credentials содержат его
 * OAuth/API-данные; один Prompt может породить Post в нескольких каналах.
 *
 * @property int $id
 * @property string|null $external_account
 * @property string $name
 * @property ChannelType $type
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property array<array-key, mixed>|null $credentials
 * @property int|null $project_id Legacy owner; membership uses projects.
 * @property bool $is_active
 * @property-read Collection<int, Project> $projects
 * @property-read int|null $projects_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Post> $posts
 * @property-read int|null $posts_count
 * @property-read int|null $publication_requests_count
 * @property-read Collection<int, PublicationRequest> $publicationRequests
 * @property-read Project|null $project
 */
class Channel extends Model
{
    /** @use HasFactory<ChannelFactory> */
    use HasFactory;

    protected $fillable = [
        'external_account',
        'name',
        'type',
        'client_id',
        'client_secret',
        'credentials',
        'is_active',
    ];

    protected $hidden = ['client_secret', 'credentials'];

    protected $attributes = ['is_active' => true];

    protected $casts = [
        'is_active' => 'boolean',
        'type' => ChannelType::class,
        'credentials' => 'array',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    /** @return HasMany<PublicationRequest, $this> */
    public function publicationRequests(): HasMany
    {
        return $this->hasMany(PublicationRequest::class);
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
