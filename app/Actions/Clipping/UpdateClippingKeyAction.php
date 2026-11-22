<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Data\ClippingKeyData;
use App\Models\ClippingConnection;
use App\Repositories\ClippingConnectionRepository;

final readonly class UpdateClippingKeyAction
{
    public function __construct(private ClippingConnectionRepository $connections) {}

    public function run(ClippingConnection $connection, ClippingKeyData $data): void
    {
        $this->connections->updateKey($connection, $data->api_key);
    }
}
