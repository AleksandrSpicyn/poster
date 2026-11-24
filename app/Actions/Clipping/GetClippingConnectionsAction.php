<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Classes\Clipping\ClippingReaders;
use App\Models\ClippingConnection;
use App\Models\User;
use App\Repositories\ClippingConnectionRepository;
use Illuminate\Support\Arr;

final readonly class GetClippingConnectionsAction
{
    public function __construct(private ClippingConnectionRepository $connections, private ClippingReaders $readers) {}

    /** @return array<string, mixed> */
    public function run(User $owner): array
    {
        return [
            'drivers' => $this->readers->labels(),
            'connections' => $this->connections->owned($owner)->map(static function (ClippingConnection $connection): array {
                $access = $connection->getAttribute('access_result');

                return [
                    ...$connection->only([
                        'id',
                        'name',
                        'driver',
                        'catalog_checked_at',
                        'catalog_error',
                        'import_projects',
                    ]),
                    'access' => is_array($access) ? Arr::only($access, [
                        'projects',
                        'clips',
                        'accounts',
                        'metrics',
                    ]) : null,
                ];
            }),
        ];
    }
}
