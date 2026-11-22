<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Classes\Clipping\ClippingReader;
use App\Classes\Clipping\ClippingReaders;
use App\Data\ClippingSourceSelectionData;
use App\Http\Resources\ProjectResource;
use App\Models\ClippingConnection;
use App\Models\Project;
use App\Repositories\ClippingAccountRepository;
use App\Repositories\ClippingConnectionRepository;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class GetClippingSourcesAction
{
    public function __construct(private ClippingReaders $readers, private ClippingConnectionRepository $connections, private ClippingAccountRepository $accounts) {}

    /** @return array<string, mixed> */
    public function run(ClippingConnection $connection, ClippingSourceSelectionData $selection): array
    {
        $preview = $selection->preview === null ? null : $this->connections->getPreview($connection, $selection->preview);
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
            'connection' => $connection->only(['id', 'name', 'driver']), 'sources' => $sources, 'projects' => $this->connections->projects($connection)->map(static fn(Project $project): array => Arr::only(ProjectResource::make($project)->resolve(), ['id', 'name', 'cover'])),
            'sourceBindings' => $this->connections->mappedSources($connection),
            'previous' => $preview?->only(['id', 'external_id', 'project_id', 'accounts', 'clip_count', 'imported_at']),
            'channels' => $readsAccounts ? $this->accounts->channels($connection) : [], 'mappings' => (object) ($readsAccounts ? $this->accounts->mappings($connection) : []),
            'accounts' => $accounts, 'importProjects' => $connection->import_projects, 'importAccounts' => $connection->import_accounts, 'readsAccounts' => $readsAccounts, 'accountError' => $accountError,
        ];
    }
}
