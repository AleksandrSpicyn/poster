<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Models\ClippingConnection;
use App\Repositories\ClippingConnectionRepository;

final readonly class GetClippingPreviewAction
{
    public function __construct(private ClippingConnectionRepository $connections) {}

    /** @return array<string, mixed> */
    public function run(ClippingConnection $connection, int $previewId): array
    {
        $preview = $this->connections->getPreview($connection, $previewId);

        return [
            'connection' => $connection->only(['id', 'name']), 'preview' => $preview,
            'clips' => $preview->clips, 'projectName' => $this->connections->projects($connection)->firstWhere('id', $preview->project_id)?->name,
        ];
    }
}
