<?php

declare(strict_types=1);

use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\Series;
use App\Tasks\Episodes\BuildEpisodeContext;
use Illuminate\Validation\ValidationException;

function acceptedContextEpisode(Series $series, int $number, string $events, EpisodeValidity $validity = EpisodeValidity::CURRENT): Episode
{
    $episode = Episode::factory()->for($series)->create(['number' => $number, 'validity' => $validity]);
    $revision = EpisodeRevision::factory()->for($episode)->create();
    $assembly = EpisodeAssembly::factory()->for($revision, 'episodeRevision')->create([
        'status' => AssemblyStatus::ACCEPTED,
        'continuity_snapshot' => ['confirmed_events' => $events],
    ]);
    $episode->update(['accepted_assembly_id' => $assembly->id]);

    return $episode;
}

test('контекст связывает имя героя с его постоянным ID и утверждённой версией', function (): void {
    $series = Series::factory()->create();
    $character = Character::factory()->for($series)->create(['name' => 'Juno']);
    $revision = CharacterRevision::factory()->for($character)->approved()->create(['description' => 'A fox wearing a blue scarf.']);
    $character->update(['current_revision_id' => $revision->id]);
    $episode = Episode::factory()->for($series)->create(['number' => 1]);

    $context = app(BuildEpisodeContext::class)->run($episode);

    expect($context['characters'])->toHaveCount(1);
    expect($context['characters'][0])->toMatchArray([
        'id' => $revision->id, 'character_id' => $character->id,
        'name' => 'Juno', 'description' => 'A fox wearing a blue scarf.',
    ]);
});

test('контекст содержит только принятые события предыдущих актуальных эпизодов своего сериала', function (): void {
    $series = Series::factory()->create();
    $first = acceptedContextEpisode($series, 1, 'The key was found.');
    Episode::factory()->for($series)->create(['number' => 2]);
    acceptedContextEpisode($series, 3, 'Stale events.', EpisodeValidity::REQUIRES_REVIEW);
    $previous = acceptedContextEpisode($series, 4, 'The door stayed closed.');
    acceptedContextEpisode($series, 6, 'Future events.');
    acceptedContextEpisode(Series::factory()->create(), 1, 'Foreign events.');
    $episode = Episode::factory()->for($series)->create(['number' => 5, 'predecessor_episode_id' => $previous->id]);

    $context = app(BuildEpisodeContext::class)->run($episode);

    expect($context['history'])->toBe([
        ['episode_number' => 1, 'assembly_id' => $first->accepted_assembly_id, 'confirmed_events' => 'The key was found.'],
        ['episode_number' => 4, 'assembly_id' => $previous->accepted_assembly_id, 'confirmed_events' => 'The door stayed closed.'],
    ]);
    expect($context['source_assembly_id'])->toBe($previous->accepted_assembly_id);
    expect($context['continuity'])->toBe(['confirmed_events' => 'The door stayed closed.']);
});

test('история ограничена последними пятьюдесятью принятыми эпизодами в порядке событий', function (): void {
    $series = Series::factory()->create();

    foreach (range(1, 52) as $number) {
        acceptedContextEpisode($series, $number, 'Event ' . $number);
    }

    $episode = Episode::factory()->for($series)->create(['number' => 53]);

    $context = app(BuildEpisodeContext::class)->run($episode);

    expect(array_column($context['history'], 'episode_number'))->toBe(range(3, 52));
    expect($context['history'][0]['confirmed_events'])->toBe('Event 3');
    expect($context['history'][49]['confirmed_events'])->toBe('Event 52');
});

test('лимит контекста удаляет старые события и сохраняет точный финал источника', function (): void {
    $series = Series::factory()->create();
    acceptedContextEpisode($series, 1, str_repeat('a', 30000));
    acceptedContextEpisode($series, 2, str_repeat('b', 30000));
    $events = str_repeat('c', 30000);
    $previous = acceptedContextEpisode($series, 3, $events);
    $episode = Episode::factory()->for($series)->create(['number' => 4, 'predecessor_episode_id' => $previous->id]);

    $context = app(BuildEpisodeContext::class)->run($episode);

    expect($context['history'])->toBe([
        ['episode_number' => 3, 'assembly_id' => $previous->accepted_assembly_id, 'confirmed_events' => $events],
    ]);
    expect($context['continuity'])->toBe(['confirmed_events' => $events]);
    expect(strlen(json_encode($context, JSON_THROW_ON_ERROR)))->toBeLessThanOrEqual(64000);
});

test('слишком большой обязательный контекст отклоняется без потери фактического финала', function (): void {
    $series = Series::factory()->create();
    $previous = acceptedContextEpisode($series, 1, str_repeat('x', 65000));
    $episode = Episode::factory()->for($series)->create(['number' => 2, 'predecessor_episode_id' => $previous->id]);

    expect(fn() => app(BuildEpisodeContext::class)->run($episode))->toThrow(ValidationException::class);

    expect($previous->acceptedAssembly()->firstOrFail()->continuity_snapshot['confirmed_events'])->toBe(str_repeat('x', 65000));
});
