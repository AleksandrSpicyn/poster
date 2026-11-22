<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Classes\Clipping\ClippingReader;
use App\Classes\Clipping\ClippingReaders;
use App\Models\ClippingConnection;
use App\Repositories\ClippingAccountRepository;
use App\Repositories\ClippingConnectionRepository;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class GetClippingSourcesAction
{
    public function __construct(private ClippingReaders $readers, private ClippingConnectionRepository $connections, private ClippingAccountRepository $accounts) {}

    /** @return array<string, mixed> */
    public function run(ClippingConnection $connection): array
    {
        $reader = $connection->import_projects || $connection->import_accounts ? $this->readers->forConnection($connection) : null;
        $readsAccounts = $reader instanceof ClippingReader && $connection->import_accounts && $reader::readsAccounts();

        try {
            $sources = $connection->import_projects && $reader instanceof ClippingReader ? collect($this->readers->sources($reader, $connection))->values() : collect();
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['connection' => 'Не удалось получить проекты. Проверьте ключ и доступность провайдера.']);
        }

        $accounts = [];
        $accountError = null;

        if ($readsAccounts) {
            try {
                $accounts = $reader->accounts();
            } catch (RuntimeException) {
                $accountError = 'Провайдер не вернул список аккаунтов.';
            }
        }

        return [
            'connection' => $connection->only(['id', 'name', 'driver']), 'sources' => $sources, 'projects' => $this->connections->projects($connection),
            'channels' => $readsAccounts ? $this->accounts->channels($connection) : [], 'mappings' => (object) ($readsAccounts ? $this->accounts->mappings($connection) : []),
            'accounts' => $accounts, 'importProjects' => $connection->import_projects, 'importAccounts' => $connection->import_accounts, 'readsAccounts' => $readsAccounts, 'accountError' => $accountError,
        ];
    }
}
