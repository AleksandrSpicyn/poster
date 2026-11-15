<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Exceptions\CodexBusyException;
use App\Models\Episode;
use App\Tasks\Episodes\ClaimEpisodeScript;
use App\Tasks\Episodes\CompleteEpisodeScriptRequest;
use App\Tasks\Episodes\DeferEpisodeScriptRequest;
use App\Tasks\Episodes\GenerateEpisodeScript;
use App\Tasks\Episodes\SaveEpisodeScript;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

readonly class ProcessEpisodeScriptAction
{
    public function __construct(
        private ClaimEpisodeScript $claim,
        private GenerateEpisodeScript $generate,
        private SaveEpisodeScript $save,
        private CompleteEpisodeScriptRequest $complete,
        private DeferEpisodeScriptRequest $defer,
    ) {}

    public function run(Episode $episode, string $requestKey): void
    {
        $episode = $this->claim->run($episode, $requestKey);

        if (! $episode instanceof Episode) {
            return;
        }

        try {
            $payload = $episode->script_request_payload ?? throw new RuntimeException('Missing screenplay request.');
            $script = $this->generate->run($payload['context'], $payload['brief']);
            DB::transaction(function () use ($episode, $script, $payload, $requestKey): void {
                $this->save->run($episode, $script, $payload['expected_version'], $payload['context']);
                $this->complete->run($episode, $requestKey);
            });
        } catch (CodexBusyException) {
            $this->defer->run($episode, $requestKey);
        } catch (Throwable $exception) {
            $this->complete->run($episode, $requestKey, 'Screenplay generation failed or its source changed. Review the episode before requesting another draft.');
            report($exception);
        }
    }
}
