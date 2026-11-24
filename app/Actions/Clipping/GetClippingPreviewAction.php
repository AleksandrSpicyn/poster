<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Http\Resources\ProjectCardResource;
use App\Models\ClippingConnection;
use App\Models\Project;
use App\Repositories\ClippingAccountRepository;
use App\Repositories\ClippingConnectionRepository;

final readonly class GetClippingPreviewAction
{
    public function __construct(private ClippingConnectionRepository $connections, private ClippingAccountRepository $accounts) {}

    /** @return array<string, mixed> */
    public function run(ClippingConnection $connection, int $previewId): array
    {
        $preview = $this->connections->getPreview($connection, $previewId);

        $accountNames = [];

        if ($preview->accounts !== [] && $preview->accounts !== null) {
            $mappings = $this->accounts->mappings($connection, array_column($preview->accounts, 'id'));
            $ids = array_values(array_filter([...array_column($preview->accounts, 'channel_id'), ...array_values($mappings)]));
            $channels = $this->accounts->channels($connection, $ids)->keyBy('id');

            foreach ($preview->accounts as $account) {
                $id = $account['channel_id'] ?? $mappings[$account['id']] ?? null;

                if ($id !== null && $channels->has($id)) {
                    $accountNames[$account['id']] = $channels->get($id)?->name;
                }
            }
        }

        $project = $preview->project_id === null ? null : $this->connections->reviewProject($connection, $preview->project_id);

        return [
            'project' => $project instanceof Project ? ProjectCardResource::make($project)->resolve() : null,
            'existingClipIds' => $preview->external_id === null || $preview->imported_at !== null ? [] : $this->connections->existingClipIds($connection, $preview),
            'accountNames' => (object) $accountNames,
            'connection' => $connection->only(['id', 'name']),
            'preview' => $preview,
            'clips' => $preview->clips,
            'projectName' => $project?->name,
        ];
    }
}
