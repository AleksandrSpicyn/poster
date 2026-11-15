<?php

declare(strict_types=1);

use App\Classes\Generators\Codex\CodexTextGateway;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Tools\LoadSkill;

use function Laravel\Ai\agent;
use function Tests\Support\fakeCodex;

test('сохраняет инструкции и диалог с именем провайдера и явной моделью', function (): void {
    config(['ai.providers.other-codex' => ['driver' => 'codex', 'key' => '', 'model' => 'configured-model']]);
    fakeCodex(function (PendingProcess $process): ProcessResult {
        File::put(($process->path ?? '') . '/result.json', '{"text":"A new idea"}');

        return Process::result();
    });

    $response = agent(instructions: 'Write short stories.', messages: [new UserMessage('Earlier request'), new AssistantMessage('Earlier answer')])
        ->prompt('Next idea', provider: 'other-codex', model: 'explicit-model', timeout: 27);

    expect($response->text)->toBe('A new idea');
    expect($response->meta->provider)->toBe('other-codex');
    expect($response->meta->model)->toBe('explicit-model');
    Process::assertRan(function (PendingProcess $process): bool {
        if (! is_string($process->input)) {
            return false;
        }

        expect($process->timeout)->toBe(27);
        expect($process->command)->toContain('explicit-model');
        expect(json_decode(Str::after($process->input, "\n"), true, flags: JSON_THROW_ON_ERROR))->toBe([
            'instructions' => 'Write short stories.',
            'messages' => [
                ['role' => 'user', 'content' => 'Earlier request'],
                ['role' => 'assistant', 'content' => 'Earlier answer'],
                ['role' => 'user', 'content' => 'Next idea'],
            ],
        ]);

        return true;
    });
    Http::assertNothingSent();
});

test('отклоняет неподдерживаемые возможности до запуска CLI', /** @param Closure(): AgentResponse $request */ function (Closure $request): void {
    Process::fake();

    expect($request)->toThrow(InvalidArgumentException::class);

    Process::assertNothingRan();
    Http::assertNothingSent();
})->with([
    'инструменты' => fn(): AgentResponse => agent(tools: [new LoadSkill()])->prompt('Idea', provider: 'codex'),
    'структурный ответ' => fn(): AgentResponse => agent(schema: fn(JsonSchema $schema): array => ['idea' => $schema->string()->required()])->prompt('Idea', provider: 'codex'),
    'вложения' => fn(): AgentResponse => agent()->prompt('Idea', attachments: [Image::fromPath(base_path('tests/Fixtures/portrait.png'))], provider: 'codex'),
]);

test('не игнорирует неподдерживаемые параметры модели', function (TextGenerationOptions $options): void {
    Process::fake();

    expect(fn() => app(CodexTextGateway::class)->generateTextStep(
        Ai::textProvider('codex'),
        'test-model',
        null,
        [new UserMessage('Idea')],
        [],
        null,
        $options,
        30,
        new StepContext(),
    ))->toThrow(InvalidArgumentException::class);

    Process::assertNothingRan();
    Http::assertNothingSent();
})->with([
    'температура' => new TextGenerationOptions(temperature: 0.5),
    'максимум токенов' => new TextGenerationOptions(maxTokens: 100),
    'top p' => new TextGenerationOptions(topP: 0.7),
    'provider options' => new TextGenerationOptions(providerOptions: ['unsupported' => true]),
]);

test('потоковый режим явно сообщает об ограничении', function (): void {
    Process::fake();

    expect(fn(): array => iterator_to_array(agent()->stream('Idea', provider: 'codex')))
        ->toThrow(LogicException::class, 'synchronous');

    Process::assertNothingRan();
    Http::assertNothingSent();
});
