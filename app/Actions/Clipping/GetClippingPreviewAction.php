<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Models\ClippingConnection;
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
            $channels = $this->accounts->channels($connection)->keyBy('id');
            $mappings = $this->accounts->mappings($connection);

            foreach ($preview->accounts as $account) {
                $id = $account['channel_id'] ?? $mappings[$account['id']] ?? null;

                if ($id !== null && $channels->has($id)) {
                    $accountNames[$account['id']] = $channels->get($id)?->name;
                }
            }
        }

        return [
            'accountNames' => (object) $accountNames,
            'connection' => $connection->only(['id', 'name']), 'preview' => $preview,
            'clips' => $preview->clips, 'projectName' => $this->connections->projects($connection)->firstWhere('id', $preview->project_id)?->name,
        ];
    }
}
