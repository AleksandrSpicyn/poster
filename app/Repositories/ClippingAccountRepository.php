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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final readonly class ClippingAccountRepository
{
    /** @return Collection<int, Channel> */
    public function channels(ClippingConnection $connection): Collection
    {
        return Channel::query()->where('owner_id', $connection->owner_id)->orderBy('name')->get(['id', 'name', 'type']);
    }

    /** @return array<string, int> */
    public function mappings(ClippingConnection $connection): array
    {
        return ClippingAccountMapping::query()->where('clipping_connection_id', $connection->id)->get(['provider_account_id', 'channel_id'])->mapWithKeys(static fn(ClippingAccountMapping $mapping): array => [$mapping->provider_account_id => $mapping->channel_id])->all();
    }

    /**
     * @param list<ClippingAccountSelectionData> $selections
     * @param list<array{id: string, platform: string, external_id: string, name: string}> $available
     * @return list<array{id: string, platform: string, external_id: string, name: string, channel_id: int|null}>
     */
    public function preview(ClippingConnection $connection, array $selections, array $available): array
    {
        $byId = collect($available)->keyBy('id');
        $mapped = $this->mappings($connection);
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

            if ($id !== null) {
                Channel::query()->where('owner_id', $connection->owner_id)->where('type', $type)->findOrFail($id);
            }

            $result[$selection->id] = [...$account, 'channel_id' => $id];
        }

        return array_values($result);
    }

    public function attach(ClippingConnection $connection, Project $project, ClippingImport $preview): void
    {
        foreach ($preview->accounts ?? [] as $account) {
            $mapping = ClippingAccountMapping::query()->where('clipping_connection_id', $connection->id)
                ->where('provider_account_id', $account['id'])->lockForUpdate()->first();

            if ($mapping !== null && ($mapping->external_id !== $account['external_id'] || $mapping->platform !== $account['platform']
                || ($account['channel_id'] !== null && $mapping->channel_id !== $account['channel_id']))) {
                throw ValidationException::withMessages(['accounts' => 'Привязка изменилась. Повторите предпросмотр.']);
            }

            $id = $mapping->channel_id ?? $account['channel_id'];
            $channel = $id === null
                ? new Channel(['name' => $account['name'] ?: $account['external_id'], 'type' => $account['platform'], 'is_active' => false])
                : Channel::query()->where('owner_id', $connection->owner_id)->where('type', $account['platform'])->lockForUpdate()->findOrFail($id);

            if (! $channel->exists) {
                $channel->owner_id = $connection->owner_id;
                $channel->save();
            }

            if ($mapping === null) {
                ClippingAccountMapping::query()->insert([
                    'clipping_connection_id' => $connection->id, 'provider_account_id' => $account['id'],
                    'external_id' => $account['external_id'], 'platform' => $account['platform'], 'channel_id' => $channel->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $project->channels()->syncWithoutDetaching([$channel->id]);
        }
    }
}
