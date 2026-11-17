<?php

declare(strict_types=1);

use App\Actions\Episodes\SaveEpisodeScriptAction;
use App\Data\Episodes\SaveEpisodeScriptData;
use App\Tasks\Production\AssertProductionScreenplay;
use Illuminate\Validation\ValidationException;

use function Tests\Support\storyFixture;
use function Tests\Support\storyScriptPayload;

test('минутная серия состоит из четырёх генераций по пятнадцать секунд', function (bool $longShots): void {
    config(['services.video.provider' => 'seedance', 'services.seedance.model' => 'dreamina-seedance-2-5-flash-261115']);
    $context = storyFixture();
    $series = $context['episode']->series()->firstOrFail();
    $series->update(['specification' => [...$series->specification, 'episode_duration_seconds' => 60, 'shot_duration_seconds' => 15]]);

    $script = storyScriptPayload($context['character']);
    $shots = [];

    foreach (range(1, $longShots ? 2 : 4) as $number) {
        $shots[] = ['duration_ms' => $longShots ? 30000 : 15000, 'action' => 'Story step ' . $number,
            'camera' => 'Medium shot', 'transition_mode' => 'new_scene', 'dialogue' => []];
    }

    $scenes = $script['scenes'];

    if (! is_array($scenes) || ! is_array($scenes[0] ?? null)) {
        throw new LogicException('Invalid fixture');
    }

    $scenes[0]['shots'] = $shots;
    $script['scenes'] = $scenes;
    $revision = app(SaveEpisodeScriptAction::class)->run($context['episode'], SaveEpisodeScriptData::validateAndCreate(['script' => $script, 'lock_version' => 0]));

    if ($longShots) {
        expect(fn() => app(AssertProductionScreenplay::class)->run($revision))->toThrow(ValidationException::class);
    } else {
        app(AssertProductionScreenplay::class)->run($revision);

        expect($revision->scenes()->firstOrFail()->shots()->pluck('duration_ms')->all())->toBe([15000, 15000, 15000, 15000]);
    }
})->with([false, true]);
