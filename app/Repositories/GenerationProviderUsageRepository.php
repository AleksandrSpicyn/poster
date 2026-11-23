<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\VideoUsageData;
use App\Enums\VideoInputMode;
use App\Enums\VideoProvider;
use App\Models\GenerationOperation;
use App\Models\GenerationProviderUsage;
use App\Models\Prompt;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerationProviderUsageRepository
{
    public function record(VideoProvider $provider, string $externalId, Prompt|GenerationOperation $work, VideoUsageData $usage): void
    {
        $shot = $work instanceof GenerationOperation;
        $settings = $shot ? ($work->request_snapshot['video'] ?? []) : ($work->input_snapshot['video_request'] ?? []);
        $settings = is_array($settings) ? $settings : [];

        $promptId = $shot ? $work->prompt_id : $work->id;
        $details = array_filter([
            'operation_id' => $shot ? $work->id : null,
            'requested_model' => $settings['model'] ?? null,
            'result_model' => $usage->model,
            'duration' => $usage->duration,
            'input_mode' => $shot ? VideoInputMode::FIRST_FRAME->value : ($settings['mode'] ?? null),
            'resolution' => $usage->resolution ?? $settings['resolution'] ?? null,
            'completion_tokens' => $usage->completionTokens,
        ], static fn(mixed $value): bool => is_int($value) || is_string($value));

        DB::transaction(function () use ($provider, $externalId, $promptId, $usage, $details): void {
            $key = ['provider' => $provider->value, 'external_id' => $externalId];
            $query = GenerationProviderUsage::query()->where($key);
            $query->upsert(
                [...$key, 'prompt_id' => $promptId, 'status' => $usage->status->value, 'observed_at' => now('UTC'), ...$details],
                ['provider', 'external_id'],
                ['external_id'],
            );
            $stored = $query->lockForUpdate()->firstOrFail();

            if ($stored->prompt_id !== $promptId) {
                throw ValidationException::withMessages(['external_id' => 'Эта попытка генерации уже связана с другим дублем.']);
            }

            $missing = array_filter($details, static fn(string $field): bool => $stored->{$field} === null, ARRAY_FILTER_USE_KEY);

            if ($missing !== []) {
                $query->update($missing);
            }
        }, 3);
    }

    /** @return Collection<int, GenerationProviderUsage> */
    public function observedBetween(CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        return GenerationProviderUsage::query()
            ->where('observed_at', '>=', $from->utc())
            ->where('observed_at', '<', $until->utc())
            ->orderBy('observed_at')
            ->orderBy('id')
            ->get(['provider', 'external_id', 'result_model', 'requested_model', 'input_mode', 'resolution', 'status', 'duration', 'completion_tokens', 'charge_e8', 'currency', 'finance_reference']);
    }

    public function reconcile(VideoProvider $provider, string $externalId, int $charge, string $currency, string $reference): void
    {
        DB::transaction(function () use ($provider, $externalId, $charge, $currency, $reference): void {
            $query = GenerationProviderUsage::query()->where('provider', $provider->value)->where('external_id', $externalId);
            $row = $query->lockForUpdate()->first();

            if ($row === null) {
                throw new DomainException('Попытка генерации не найдена.');
            }

            if ($row->reconciled_at !== null) {
                if ($row->charge_e8 !== $charge || $row->currency !== $currency || $row->finance_reference !== $reference) {
                    throw new DomainException('Для этой попытки уже сохранено другое начисление.');
                }

                return;
            }

            $query->update(['charge_e8' => $charge, 'currency' => $currency, 'finance_reference' => $reference, 'reconciled_at' => now('UTC')]);
        }, 3);
    }
}
