<?php

declare(strict_types=1);

use App\Classes\Generators\PromptGenerator\PromptGenerator;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Prompts\AgentPrompt;

use function Tests\Support\fakeCodex;

test('генерирует текст через подписку с выбранной моделью', function (): void {
    config(['ai.providers.codex.model' => 'test-codex-model', 'services.codex.timeout' => 45]);
    fakeCodex(function (PendingProcess $process): ProcessResult {
        File::put(($process->path ?? '') . '/result.json', json_encode(['text' => "  What if a fox found a fountain? It taps the water.\n"], JSON_THROW_ON_ERROR));

        return Process::result();
    });
    $prompt = 'Original idea; $(do-not-run) `not-a-command`';

    $text = app(PromptGenerator::class)->generate($prompt);

    expect($text)->toBe('What if a fox found a fountain? It taps the water.');
    Process::assertRan(fn(PendingProcess $process): bool => is_array($process->command)
        && in_array('test-codex-model', $process->command, true)
        && in_array('read-only', $process->command, true)
        && in_array('features.image_generation=false', $process->command, true)
        && in_array('forced_login_method="chatgpt"', $process->command, true)
        && in_array('features.shell_tool=false', $process->command, true)
        && in_array('--ignore-user-config', $process->command, true)
        && in_array('--ignore-rules', $process->command, true)
        && $process->environment['OPENAI_API_KEY'] === false
        && $process->environment['CODEX_API_KEY'] === false
        && $process->timeout === 45
        && is_string($process->input) && str_contains($process->input, $prompt)
        && ! in_array($prompt, $process->command, true)
        && $process->path !== null && ! File::exists($process->path));
    Http::assertNothingSent();
});

test('поддерживает fake Laravel AI без запуска CLI', function (): void {
    Process::fake();
    AnonymousAgent::fake(['  New idea  '])->preventStrayPrompts();

    $text = app(PromptGenerator::class)->generate('Project brief');

    expect($text)->toBe('New idea');
    AnonymousAgent::assertPrompted(fn(AgentPrompt $prompt): bool => $prompt->provider->name() === 'codex' && $prompt->prompt === 'Project brief');
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('отклоняет пустой текст даже от fake', function (): void {
    Process::fake();
    AnonymousAgent::fake(['  '])->preventStrayPrompts();

    expect(fn() => app(PromptGenerator::class)->generate('Project brief'))->toThrow(UnexpectedValueException::class);

    Process::assertNothingRan();
    Http::assertNothingSent();
});
