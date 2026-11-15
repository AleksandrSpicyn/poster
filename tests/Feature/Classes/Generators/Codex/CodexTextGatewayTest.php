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
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
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
    'документы' => fn(): AgentResponse => agent()->prompt('Idea', attachments: [Document::fromPath(base_path('README.md'))], provider: 'codex'),
]);

test('передаёт кадры текстовой модели без разрешения генерации изображений', function (): void {
    $referencePath = '';
    fakeCodex(function (PendingProcess $process) use (&$referencePath): ProcessResult {
        $arguments = $process->command;

        if (! is_array($arguments)) {
            throw new LogicException('The CLI command must be an argument list.');
        }

        expect($arguments)->toContain('--image', 'read-only', 'features.shell_tool=false', 'features.image_generation=false');
        $index = array_search('--image', $arguments, true);

        if (! is_int($index)) {
            throw new LogicException('The image attachment is missing.');
        }

        $referencePath = $arguments[$index + 1];
        expect(File::get($referencePath))->toBe(File::get(base_path('tests/Fixtures/portrait.png')));
        File::put(($process->path ?? '') . '/result.json', '{"text":"The cat is resting."}');

        return Process::result();
    });

    $response = agent()->prompt(
        'Describe only visible events.',
        attachments: [Image::fromPath(base_path('tests/Fixtures/portrait.png'))],
        provider: 'codex',
    );

    expect($response->text)->toBe('The cat is resting.');
    expect(File::exists($referencePath))->toBeFalse();
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
    Http::assertNothingSent();
});

test('передаёт вложенную схему CLI и возвращает структурированный ответ SDK', function (): void {
    fakeCodex(function (PendingProcess $process): ProcessResult {
        $schema = json_decode(File::get(($process->path ?? '') . '/schema.json'), true, flags: JSON_THROW_ON_ERROR);
        expect($schema)->toMatchArray([
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'scenes'],
        ]);
        expect(data_get($schema, 'properties.scenes.items.additionalProperties'))->toBeFalse();
        File::put(($process->path ?? '') . '/result.json', '{"title":"Серия","scenes":[{"action":"Входит герой","closed":false}]}');

        return Process::result();
    });

    $response = agent(instructions: 'Create a serial episode.', schema: fn(JsonSchema $schema): array => [
        'title' => $schema->string()->required(),
        'scenes' => $schema->array()->items($schema->object([
            'action' => $schema->string()->required(),
            'closed' => $schema->boolean()->required(),
        ]))->required(),
    ])->prompt('Continue the story', provider: 'codex');

    expect($response)->toBeInstanceOf(StructuredAgentResponse::class);
    expect($response['title'])->toBe('Серия');
    expect($response['scenes'])->toBe([['action' => 'Входит герой', 'closed' => false]]);
    Process::assertRan(function (PendingProcess $process): bool {
        expect($process->input)->toContain('Create a serial episode.', 'without a text wrapper');
        expect($process->command)->toContain('features.shell_tool=false', 'read-only');

        return true;
    });
    Http::assertNothingSent();
});

test('не подменяет повреждённый структурированный ответ обычным текстом', function (): void {
    fakeCodex(function (PendingProcess $process): ProcessResult {
        File::put(($process->path ?? '') . '/result.json', '```json {"title":"Серия"} ```');

        return Process::result();
    });

    expect(fn(): AgentResponse => agent(schema: fn(JsonSchema $schema): array => ['title' => $schema->string()->required()])
        ->prompt('Episode', provider: 'codex'))->toThrow(JsonException::class);

    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
    Http::assertNothingSent();
});

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
