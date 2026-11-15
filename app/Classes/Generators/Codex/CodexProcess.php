<?php

declare(strict_types=1);

namespace App\Classes\Generators\Codex;

use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/** Запускает изолированную CLI-сессию; результат читается до очистки её каталога. */
readonly class CodexProcess
{
    public function __construct(
        #[Config('services.codex.binary')] private string $binary,
        #[Config('services.codex.home')] private string $home,
        #[Config('services.codex.timeout')] private int $timeout,
    ) {}

    /**
     * @template TResult
     *
     * @param array<string, mixed> $schema
     * @param Closure(array<array-key, mixed>, string, string): TResult $readResult
     * @return TResult
     */
    public function run(
        string $prompt,
        array $schema,
        Closure $readResult,
        ?string $model = null,
        bool $images = false,
        ?int $timeout = null,
    ): mixed {
        $timeout ??= $this->timeout;

        if ($timeout <= 0) {
            throw new InvalidArgumentException('Codex timeout must be positive.');
        }

        $home = realpath($this->home);

        if ($home === false || ! File::isFile($home . '/auth.json')) {
            throw new RuntimeException('Codex ChatGPT login is missing. Run codex login --device-auth with the configured CODEX_HOME.');
        }

        $lock = Cache::lock('codex:' . hash('sha256', $home), $timeout + 30);

        if (! $lock->get()) {
            throw new RuntimeException('Codex generation is already running.');
        }

        $workspace = sys_get_temp_dir() . '/poster-codex-' . Str::uuid();

        try {
            File::makeDirectory($workspace, 0700, true);
            File::put($workspace . '/schema.json', json_encode($schema, JSON_THROW_ON_ERROR));

            $result = Process::path($workspace)
                ->env(['CODEX_HOME' => $home, 'OPENAI_API_KEY' => false, 'CODEX_API_KEY' => false])
                ->timeout($timeout)
                ->input($prompt)
                ->run($this->command($workspace, $model, $images));

            if ($result->failed()) {
                throw new RuntimeException('Codex generation failed. Check ChatGPT login, subscription limits, and CLI availability.');
            }

            $path = $workspace . '/result.json';

            if (! File::isFile($path) || File::size($path) > 1024 * 1024) {
                throw new RuntimeException('Codex returned no result or exceeded the result size limit.');
            }

            $response = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($response) || array_is_list($response)) {
                throw new RuntimeException('Codex must return a JSON object.');
            }

            return $readResult($response, $workspace, $home);
        } finally {
            File::deleteDirectory($workspace);
            $lock->release();
        }
    }

    /** @return list<string> */
    private function command(string $workspace, ?string $model, bool $images): array
    {
        $command = [
            $this->binary, 'exec', '--ignore-user-config', '--ignore-rules', '--skip-git-repo-check', '--ephemeral',
            '--sandbox', $images ? 'workspace-write' : 'read-only',
            '-c', 'forced_login_method="chatgpt"',
            '-c', 'cli_auth_credentials_store="file"',
            '-c', 'approval_policy="never"',
            '-c', 'features.image_generation=' . ($images ? 'true' : 'false'),
            '-c', 'features.shell_tool=false',
            '-c', 'features.unified_exec=false',
            '-c', 'web_search="disabled"',
            '-c', 'shell_environment_policy.inherit="none"',
            '--output-schema', $workspace . '/schema.json',
            '--output-last-message', $workspace . '/result.json',
        ];

        if ($model !== null) {
            $command[] = '--model';
            $command[] = $model;
        }

        return [...$command, '-'];
    }
}
