<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Classes\Clipping\ClippingReaders;
use App\Data\ClippingScopeData;
use App\Models\ClippingConnection;
use App\Repositories\ClippingConnectionRepository;
use Illuminate\Validation\ValidationException;

final readonly class UpdateClippingScopeAction
{
    public function __construct(private ClippingConnectionRepository $connections, private ClippingReaders $readers) {}

    public function run(ClippingConnection $connection, ClippingScopeData $data): void
    {
        if ($data->import_archives && (! $data->import_projects || ! $this->readers->supportsArchives($connection->driver))) {
            throw ValidationException::withMessages(['import_archives' => 'Архивные проекты недоступны для выбранной области импорта.']);
        }

        if ($data->import_accounts && ! $this->readers->supportsAccounts($connection->driver)) {
            throw ValidationException::withMessages(['import_accounts' => 'Драйвер не предоставляет список аккаунтов.']);
        }

        if ($data->project_id !== null) {
            abort_unless($this->connections->projects($connection)->contains('id', $data->project_id), 404);
        }

        $this->connections->updateScope($connection, $data);
    }
}
