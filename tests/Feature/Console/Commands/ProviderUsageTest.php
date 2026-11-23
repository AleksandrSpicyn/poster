<?php

declare(strict_types=1);

use App\Data\VideoUsageData;
use App\Enums\VideoProvider;
use App\Enums\VideoReceiptStatus;
use App\Models\Prompt;
use App\Repositories\GenerationProviderUsageRepository;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Tests\Support\consoleCommand;

test('повторный poll дополняет квитанцию и сохраняет подтверждённое начисление', function (): void {
    Carbon::setTestNow('2026-11-23 01:00:00 Europe/Moscow');
    $prompt = Prompt::factory()->create();
    $repo = app(GenerationProviderUsageRepository::class);
    $repo->record(VideoProvider::SEEDANCE, 'remote-57', $prompt, new VideoUsageData(VideoReceiptStatus::SUCCEEDED));

    $args = ['provider' => 'seedance', 'external_id' => 'remote-57', 'amount' => '0.01234567', 'currency' => 'USD', 'reference' => 'invoice-line-57'];
    consoleCommand('billing:provider-charge', $args)->assertSuccessful();

    $repo->record(VideoProvider::SEEDANCE, 'remote-57', $prompt, new VideoUsageData(VideoReceiptStatus::SUCCEEDED, 0));
    consoleCommand('billing:provider-charge', $args)->assertSuccessful();
    consoleCommand('billing:provider-charge', [...$args, 'amount' => '1'])->assertFailed();

    assertDatabaseCount('generation_provider_usage', 1);
    assertDatabaseHas('generation_provider_usage', ['prompt_id' => $prompt->id, 'observed_at' => '2026-11-22 22:00:00', 'completion_tokens' => 0, 'charge_e8' => 1_234_567, 'currency' => 'USD', 'finance_reference' => 'invoice-line-57']);
    consoleCommand('billing:provider-usage', ['date' => '2026-11-23', '--timezone' => 'Europe/Moscow'])->expectsOutputToContain('0.01234567 USD')->assertSuccessful();
    consoleCommand('billing:provider-usage', ['date' => '2026-11-23'])->doesntExpectOutputToContain('remote-57')->assertSuccessful();
});

test('один внешний идентификатор генератора нельзя переназначить другому дублю', function (): void {
    $first = Prompt::factory()->create();
    $other = Prompt::factory()->create();
    $repo = app(GenerationProviderUsageRepository::class);
    $receipt = new VideoUsageData(VideoReceiptStatus::SUCCEEDED);
    $repo->record(VideoProvider::SEEDANCE, 'remote-5', $first, $receipt);

    expect(fn() => $repo->record(VideoProvider::SEEDANCE, 'remote-5', $other, $receipt))->toThrow(ValidationException::class);

    assertDatabaseHas('generation_provider_usage', ['prompt_id' => $first->id, 'charge_e8' => null, 'completion_tokens' => null]);
});
