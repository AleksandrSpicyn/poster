<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\ChannelCredentials;
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
 * @property int|null $owner_id
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
        'credentials' => ChannelCredentials::class,
    ];

    public function canSendTiktokInboxVideo(): bool
    {
        return $this->external_account === null && $this->hasTiktokScope('video.upload');
    }

    public function canDirectPostTiktok(): bool
    {
        return $this->external_account === null && config()->boolean('services.tiktok.direct_post') && $this->hasTiktokScope('video.publish');
    }

    public function canVerifyPublication(): bool
    {
        return $this->is_active && match ($this->type) {
            ChannelType::TIKTOK => $this->hasTiktokScope('video.list'),
            ChannelType::INSTAGRAM, ChannelType::YOUTUBE => filled($this->credentials['access_token'] ?? null),
            ChannelType::VK => false,
        };
    }

    public function hasTiktokScope(string $permission): bool
    {
        $scope = $this->credentials['scope'] ?? null;

        return $this->is_active && $this->type === ChannelType::TIKTOK && filled($this->credentials['access_token'] ?? null)
            && is_string($scope) && in_array($permission, explode(',', $scope), true);
    }

    public function oauthClientId(): ?string
    {
        return $this->oauthSetting('client_id', $this->client_id);
    }

    public function oauthClientSecret(): ?string
    {
        return $this->oauthSetting('client_secret', $this->client_secret);
    }

    private function oauthSetting(string $key, ?string $override): ?string
    {
        if ($this->client_id !== null || $this->client_secret !== null || ! in_array($this->type, ChannelType::oauthProviders(), true)) {
            return $override;
        }

        $value = config('services.' . $this->type->value . '.' . $key);

        return is_string($value) ? $value : null;
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

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
