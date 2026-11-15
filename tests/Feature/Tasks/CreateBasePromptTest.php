<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Prompt;
use App\Settings\GeneralSettings;
use App\Tasks\CreateBasePrompt;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Prompts\AgentPrompt;

test('передаёт Codex описание проекта и только последние 50 его идей', function (): void {
    GeneralSettings::fake(['duration' => 8]);
    $project = Project::factory()->create(['prompt' => 'Creative brief about curious animals.']);
    Prompt::factory()->for($project)->count(52)->sequence(fn(Sequence $sequence): array => [
        'prompt' => 'Previous [' . $sequence->index . ']',
        'created_at' => now()->subSeconds(52 - $sequence->index),
    ])->create();
    Prompt::factory()->create(['prompt' => 'Another project idea']);
    Process::fake();
    AnonymousAgent::fake(['A new original idea'])->preventStrayPrompts();

    $idea = app(CreateBasePrompt::class)->run($project);

    expect($idea)->toBe('A new original idea');
    AnonymousAgent::assertPrompted(function (AgentPrompt $prompt) use ($project): bool {
        expect($prompt->provider->name())->toBe('codex');
        expect($prompt->prompt)
            ->toContain($project->prompt, '8-second vertical short', 'visual hook', 'escalation', 'payoff', ...collect(range(2, 51))->map(fn(int $index): string => 'Previous [' . $index . ']')->all());
        expect($prompt->prompt)->not->toContain('Previous [0]', 'Previous [1]', 'Another project idea');

        return true;
    });
    Process::assertNothingRan();
    Http::assertNothingSent();
});
