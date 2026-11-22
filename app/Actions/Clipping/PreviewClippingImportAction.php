<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Classes\Clipping\ClippingReaders;
use App\Data\ClippingImportData;
use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Models\Project;
use App\Repositories\ClippingAccountRepository;
use App\Repositories\ClippingConnectionRepository;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class PreviewClippingImportAction
{
    public function __construct(private ClippingReaders $readers, private ClippingConnectionRepository $connections, private ClippingAccountRepository $accounts) {}

    public function run(ClippingConnection $connection, ClippingImportData $data): ClippingImport
    {
        if ($data->project_id !== null) {
            abort_unless($this->connections->availableProject($connection, $data->project_id) instanceof Project, 404);
        }

        if (! $connection->import_accounts && $data->accounts !== []) {
            throw ValidationException::withMessages(['accounts' => 'Связи с аккаунтами исключены из области импорта.']);
        }

        if (! $connection->import_projects && ($data->project_id === null || $data->accounts === [] || $data->external_id !== null)) {
            throw ValidationException::withMessages(['project_id' => 'Для импорта связей выберите проект Poster и аккаунты.']);
        }

        if ($connection->import_projects && $data->external_id === null) {
            throw ValidationException::withMessages(['external_id' => 'Выберите внешний проект.']);
        }

        $reader = $this->readers->forConnection($connection);

        try {
            $available = $connection->import_accounts && $reader::readsAccounts() ? $reader->accounts() : [];
        } catch (RuntimeException) {
            if ($data->accounts !== []) {
                throw ValidationException::withMessages(['accounts' => 'Провайдер не вернул выбранные аккаунты. Повторите попытку.']);
            }

            $available = [];
        }

        try {
            if ($connection->import_projects) {
                foreach ($this->readers->sources($reader, $connection) as $candidate) {
                    if ($candidate->id === $data->external_id) {
                        $source = $candidate;
                        $clips = $reader->clips($source->id);

                        break;
                    }
                }
            }
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['external_id' => 'Провайдер не вернул данные для предпросмотра. Повторите попытку.']);
        }

        if ($connection->import_projects && ! isset($source)) {
            throw ValidationException::withMessages(['external_id' => 'Источник недоступен этому подключению.']);
        }

        $selected = $this->accounts->preview($connection, $data->accounts, $available);
        $selectedIds = array_fill_keys(array_column($selected, 'id'), true);
        $unmapped = [];

        foreach ($available as $account) {
            if (! isset($selectedIds[$account['id']])) {
                $unmapped[] = ['id' => $account['id'], 'name' => $account['name'], 'platform' => $account['platform']];
            }
        }

        return $this->connections->preview($connection, $source ?? null, $data, $clips ?? null, $selected, $unmapped);
    }
}
