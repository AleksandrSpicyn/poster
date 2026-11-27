<?php

declare(strict_types=1);

use App\Models\Series;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Tests\Support\serialOperator;
use function Tests\Support\storyFixture;
use function Tests\Support\storyScriptPayload;

test('импорт сезона закрепляет говорящего и видимых персонажей в каждом кадре', function (): void {
    Storage::fake('local');
    ['episode' => $episode, 'character' => $revision] = storyFixture();
    $series = $episode->series()->firstOrFail();
    $name = $revision->character()->firstOrFail()->name;
    $script = storyScriptPayload($revision);
    /** @var array<string, mixed> $shot */
    $shot = data_get($script, 'scenes.0.shots.0');
    $shot['visible'] = [$name];
    $shot['direction'] = ['notes' => 'Слышен звон.', 'review_frame' => true];
    $shot['dialogue'] = [['speaker' => $name, 'text' => 'Я здесь.', 'start_ms' => 250, 'end_ms' => 3000]];
    $script = array_replace_recursive($script, ['scenes' => [['shots' => [$shot, $shot]]]]);
    $script['cast'] = [$name];
    $script['target_duration_seconds'] = 10;
    $bytes = json_encode(['format' => 'poster.season.v1', 'episodes' => [$script]], JSON_THROW_ON_ERROR);
    $asset = $series->addMediaFromString($bytes)->usingFileName('season.json')->withCustomProperties(['kind' => 'season', 'checksum' => hash('sha256', $bytes)])->toMediaCollection(Series::ASSETS);
    $url = route('episodes.scripts.season', ['series' => $series, 'episode' => $episode, 'assetId' => $asset->id]);

    actingAs(serialOperator())->post($url, ['lock_version' => 0])->assertSessionHasNoErrors()->assertRedirect();

    $saved = $episode->refresh()->currentRevision()->firstOrFail();
    expect($saved->context_snapshot['target_duration_seconds'])->toBe(10);

    foreach ($saved->scenes()->firstOrFail()->shots as $plan) {
        expect($plan->direction()?->notes)->toBe('Слышен звон.')
            ->and($plan->direction()?->visible_character_ids)->toBe([$revision->character_id])
            ->and($plan->dialogue[0]['start_ms'])->toBe(250);
    }

    actingAs(serialOperator())->postJson($url, ['lock_version' => 0])->assertUnprocessable()->assertJsonValidationErrors('lock_version');
});
