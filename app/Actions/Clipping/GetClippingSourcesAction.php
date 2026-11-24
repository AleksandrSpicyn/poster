<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Classes\Clipping\ClippingReader;
use App\Classes\Clipping\ClippingReaders;
use App\Data\ClippingSourceSelectionData;
use App\Data\OpusClip\OpusSourceData;
use App\Models\ClippingConnection;
use App\Repositories\ClippingAccountRepository;
use App\Repositories\ClippingConnectionRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use stdClass;

final readonly class GetClippingSourcesAction
{
    public function __construct(private ClippingReaders $readers, private ClippingConnectionRepository $connections, private ClippingAccountRepository $accounts) {}

    /** @return array<string, mixed> */
    public function run(ClippingConnection $connection, ClippingSourceSelectionData $selection): array
    {
        $preview = $selection->preview === null ? null : $this->connections->getPreview($connection, $selection->preview);
        $reader = $connection->import_projects || $connection->import_accounts ? $this->readers->forConnection($connection) : null;
        $readsAccounts = $reader instanceof ClippingReader && $connection->import_accounts && $reader::readsAccounts();

        $search = trim($selection->search ?? '');

        $accounts = fn(): array => once(fn(): array => $this->providerAccounts($readsAccounts ? $reader : null));

        return [
            'search' => $search,
            'connection' => $connection->only([
                'id',
                'name',
                'driver',
            ]),
            'sources' => fn(): array => $this->matchingSources($connection, $reader, $search),
            'sourceBindings' => fn(): Collection => $this->connections->mappedSources($connection),
            'previous' => $preview?->only([
                'id',
                'external_id',
                'project_id',
                'accounts',
                'clip_count',
                'imported_at',
            ]),
            'channels' => fn(): Collection|array => $readsAccounts ? $this->accounts->channels($connection) : [],
            'mappings' => fn(): stdClass => (object) ($readsAccounts ? $this->accounts->mappings($connection) : []),
            'accounts' => fn(): array => $accounts()['accounts'],
            'importProjects' => $connection->import_projects,
            'importAccounts' => $connection->import_accounts,
            'readsAccounts' => $readsAccounts,
            'accountError' => fn(): ?string => $accounts()['error'],
        ];
    }

    /** @return list<OpusSourceData> */
    private function matchingSources(ClippingConnection $connection, ?ClippingReader $reader, string $search): array
    {
        if (! $connection->import_projects || ! $reader instanceof ClippingReader) {
            return [];
        }

        $matches = [];

        try {
            foreach ($this->readers->sources($reader, $connection) as $source) {
                if ($search === '' || mb_stripos($source->title ?? '', $search) !== false) {
                    $matches[] = $source;
                }
            }
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['connection' => 'Не удалось получить проекты. Проверьте ключ и доступность провайдера.']);
        }

        return $matches;
    }

    /** @return array{accounts: list<array{id: string, platform: string, external_id: string, name: string}>, error: ?string} */
    private function providerAccounts(?ClippingReader $reader): array
    {
        try {
            return ['accounts' => $reader?->accounts() ?? [], 'error' => null];
        } catch (RuntimeException) {
            return ['accounts' => [], 'error' => 'Провайдер не вернул список аккаунтов.'];
        }
    }
}
