<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

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
        $reader = $this->readers->forConnection($connection);

        try {
            $sources = collect($reader->sources())->values();
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['connection' => 'Не удалось получить проекты. Проверьте ключ и доступность провайдера.']);
        }

        $accounts = [];
        $accountError = null;

        if ($reader::readsAccounts()) {
            try {
                $accounts = $reader->accounts();
            } catch (RuntimeException) {
                $accountError = 'Провайдер не вернул список аккаунтов. Импорт клипов доступен.';
            }
        }

        return [
            'connection' => $connection->only(['id', 'name', 'driver']), 'sources' => $sources, 'projects' => $this->connections->projects($connection),
            'channels' => $this->accounts->channels($connection), 'mappings' => (object) $this->accounts->mappings($connection),
            'accounts' => $accounts, 'readsAccounts' => $reader::readsAccounts(), 'accountError' => $accountError,
        ];
    }
}
