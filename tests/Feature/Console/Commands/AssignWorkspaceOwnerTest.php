<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\OpusSource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

test('назначает только подтверждённую полную группу и сохраняет идентификаторы', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => null]);
    $channel = Channel::factory()->for($project)->create(['owner_id' => null]);
    $source = OpusSource::factory()->for($project)->create();
    $card = Project::factory()->create(['owner_id' => null, 'opus_source_id' => $source->id]);
    $arguments = ['user' => $owner->id, '--project' => [$project->id], '--channel' => [$channel->id]];

    expect(fn() => Artisan::call('workspace:assign', $arguments))->toThrow(RuntimeException::class)
        ->and($project->refresh()->owner_id)->toBeNull()->and($channel->refresh()->owner_id)->toBeNull();
    $arguments['--project'][] = $card->id;
    expect(Artisan::call('workspace:assign', $arguments))->toBe(0)
        ->and(Artisan::call('workspace:assign', $arguments))->toBe(0);

    expect($channel->refresh()->owner_id)->toBe($owner->id)
        ->and($project->channels()->sole()->id)->toBe($channel->id)
        ->and($card->refresh()->owner_id)->toBe($owner->id)
        ->and($card->opus_source_id)->toBe($source->id);
});
