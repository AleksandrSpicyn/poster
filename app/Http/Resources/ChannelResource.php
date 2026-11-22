<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Channel */
class ChannelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'type' => $this->type->value,
            'type_label' => $this->type->label(), 'is_active' => $this->is_active,
            'tiktok_inbox' => $this->canSendTiktokInboxVideo(), 'tiktok_direct' => $this->canDirectPostTiktok(),
            'tiktok_statistics' => $this->hasTiktokScope('video.list'),
            'connected' => filled($this->credentials['access_token'] ?? null),
            'permission_labels' => $this->permissionLabels(),
            'account_name' => is_string($this->credentials['account_name'] ?? null) ? $this->credentials['account_name'] : null,
            'expires_at' => is_string($this->credentials['expires_at'] ?? null) ? $this->credentials['expires_at'] : null,
            'client_id' => $this->client_id, 'has_client_secret' => filled($this->client_secret),
            'oauth_configured' => filled($this->oauthClientId()) && filled($this->oauthClientSecret()),
            'can_delete' => $this->relationLoaded('projects') && $this->projects->isEmpty() && $this->posts_count === 0 && $this->publication_requests_count === 0,
            'posts_count' => $this->whenCounted('posts'),
            'projects' => $this->whenLoaded('projects', fn() => $this->projects->map(fn($project): array => ['id' => $project->id, 'name' => $project->name, 'content_mode' => $project->content_mode->value])->all()),
        ];
    }

    /** @return list<string>|null */
    private function permissionLabels(): ?array
    {
        $scopes = $this->credentials['granted_scopes'] ?? null;

        if (! is_array($scopes)) {
            return null;
        }

        $labels = [
            'https://www.googleapis.com/auth/youtube.upload' => 'Публикация',
            'https://www.googleapis.com/auth/youtube.readonly' => 'Чтение канала',
            'https://www.googleapis.com/auth/yt-analytics.readonly' => 'Чтение отчётов',
            'instagram_business_basic' => 'Чтение профиля',
            'instagram_business_content_publish' => 'Публикация',
            'instagram_business_manage_insights' => 'Чтение отчётов',
        ];

        return array_values(array_filter($labels, fn(string $scope): bool => in_array($scope, $scopes, true), ARRAY_FILTER_USE_KEY));
    }
}
