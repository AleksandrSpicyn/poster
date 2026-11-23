<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Classes\Clipping\ClippingReaders;
use App\Data\ClippingConnectionData;
use App\Models\ClippingConnection;
use App\Models\User;
use App\Repositories\ClippingConnectionRepository;
use Illuminate\Validation\ValidationException;

final readonly class SaveClippingConnectionAction
{
    public function __construct(private ClippingConnectionRepository $connections, private ClippingReaders $readers, private SyncClippingConnectionAction $sync) {}

    public function run(User $owner, ClippingConnectionData $data): ClippingConnection
    {
        if (! array_key_exists($data->driver, $this->readers->labels())) {
            throw ValidationException::withMessages(['driver' => 'Выберите доступный драйвер.']);
        }

        $connection = $this->connections->save($owner, $data)->refresh();
        $this->sync->run($connection);

        return $connection;
    }
}
