<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\ClippingAccountSelectionData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\ClippingAccountMapping;
use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Models\Project;
use App\Traits\Repositories\MatchesProviderIdentifiers;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ClippingAccountRepository
{
    use MatchesProviderIdentifiers;

    public function __construct(private ChannelRepository $channels) {}

    /**
     * @param list<int>|null $ids
     * @return Collection<int, Channel>
     */
    public function channels(ClippingConnection $connection, ?array $ids = null): Collection
    {
        return Channel::query()->where('owner_id', $connection->owner_id)->when($ids !== null, fn($query) => $query->whereKey($ids))->orderBy('name')->get([
            'id',
            'name',
            'type',
        ]);
    }

    /**
     * @param list<string>|null $providerIds
     * @return array<string, int>
     */
    public function mappings(ClippingConnection $connection, ?array $providerIds = null): array
    {
        return ClippingAccountMapping::query()->where('clipping_connection_id', $connection->id)->when($providerIds !== null, fn($query) => $query->whereIn('provider_account_id', $providerIds))->get(['provider_account_id', 'channel_id'])->mapWithKeys(static fn(ClippingAccountMapping $mapping): array => [$mapping->provider_account_id => $mapping->channel_id])->all();
    }

    /**
     * @param list<ClippingAccountSelectionData> $selections
     * @param list<array{id: string, platform: string, external_id: string, name: string}> $available
     * @return list<array{id: string, platform: string, external_id: string, name: string, channel_id: int|null}>
     */
    public function preview(ClippingConnection $connection, array $selections, array $available): array
    {
        if ($selections === []) {
            return [];
        }

        $byId = collect($available)->keyBy('id');
        $mapped = $this->mappings($connection, array_column($selections, 'id'));
        $ids = [...array_values($mapped), ...array_column($selections, 'channel_id')];
        $channels = Channel::query()->where('owner_id', $connection->owner_id)->whereKey(array_filter($ids))->get(['id', 'type'])->keyBy('id');
        $result = [];

        foreach ($selections as $selection) {
            $account = $byId->get($selection->id);
            $type = $account === null ? null : ChannelType::tryFrom($account['platform']);

            if ($account === null || $type === null || isset($result[$selection->id])) {
                throw ValidationException::withMessages(['accounts' => 'Проверьте выбранные аккаунты и площадки.']);
            }

            $id = $mapped[$selection->id] ?? $selection->channel_id;

            if ($selection->channel_id !== null && $selection->channel_id !== $id) {
                throw ValidationException::withMessages(['accounts' => 'У аккаунта уже есть другое сопоставление.']);
            }

            if ($id !== null && $channels->get($id)?->type !== $type) {
                throw new ModelNotFoundException()->setModel(Channel::class, [$id]);
            }

            $result[$selection->id] = [...$account, 'channel_id' => $id];
        }

        return array_values($result);
    }

    public function attach(ClippingConnection $connection, Project $project, ClippingImport $preview): void
    {
        $accounts = $preview->accounts ?? [];

        if ($accounts === []) {
            return;
        }

        $input = $this->providerIdentifiers('clipping_account_mappings', 'provider_account_id', array_column($accounts, 'id'));
        $mappings = ClippingAccountMapping::query()->fromSub($input, 'input')
            ->leftJoin('clipping_account_mappings as mapping', static fn(JoinClause $join) => $join->on('mapping.provider_account_id', '=', 'input.lookup_id')
                ->where('mapping.clipping_connection_id', $connection->id))
            ->select('mapping.*', 'input.lookup_id', 'input.canonical_id')->orderBy('mapping.id')->lockForUpdate()->get()->keyBy('lookup_id');
        $ids = [...$mappings->pluck('channel_id')->all(), ...array_column($accounts, 'channel_id')];
        $channels = Channel::query()->where('owner_id', $connection->owner_id)->whereKey(array_filter($ids))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $selectedIds = [];
        $newMappings = [];
        $created = [];

        foreach ($accounts as $account) {
            $match = $mappings->get($account['id']) ?? throw new LogicException('Missing import account identity.');
            $canonical = $match->canonical_id;
            $mapping = $match->getRawOriginal('id') !== null ? $match : ($created[$canonical] ?? null);

            if ($mapping !== null && ($mapping->external_id !== $account['external_id'] || $mapping->platform !== $account['platform']
                || ($account['channel_id'] !== null && $mapping->channel_id !== $account['channel_id']))) {
                throw ValidationException::withMessages(['accounts' => 'Привязка изменилась. Повторите предпросмотр.']);
            }

            $id = $mapping->channel_id ?? $account['channel_id'];
            $channel = $id === null
                ? new Channel([
                    'name' => $account['name'] ?: $account['external_id'],
                    'type' => $account['platform'],
                    'is_active' => false,
                ])
                : $channels->get($id);

            if ($channel === null || $channel->type->value !== $account['platform']) {
                throw new ModelNotFoundException()->setModel(Channel::class, $id ?? []);
            }

            if (! $channel->exists) {
                $channel->owner_id = $connection->owner_id;
                $channel->save();
            }

            if ($mapping === null) {
                $mapping = new ClippingAccountMapping([
                    'clipping_connection_id' => $connection->id,
                    'provider_account_id' => $account['id'],
                    'external_id' => $account['external_id'],
                    'platform' => $account['platform'],
                    'channel_id' => $channel->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created[$canonical] = $mapping;
                $newMappings[] = $mapping->getAttributes();
            }

            $selectedIds[] = $channel->id;
        }

        if ($newMappings !== []) {
            ClippingAccountMapping::query()->insert($newMappings);
        }

        $this->channels->assignToProject($project, $selectedIds);
    }
}
