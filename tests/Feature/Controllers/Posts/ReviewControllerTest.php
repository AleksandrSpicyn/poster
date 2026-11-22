<?php

declare(strict_types=1);

use App\Actions\Episodes\RequestEpisodePublicationAction;
use App\Data\Episodes\PublishEpisodeData;
use App\Enums\PublicationStatus;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Tests\Support\acceptedEpisodePublication;

test('показывает запрос для ручной сверки только владельцу', function (): void {
    Storage::fake('local');
    Queue::fake();
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $owner] = acceptedEpisodePublication();
    $publication = app(RequestEpisodePublicationAction::class)
        ->run($assembly, new PublishEpisodeData([$channel->id]), $owner)
        ->firstOrFail();
    $publication->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN]);

    $url = route('posts.requests.review', $publication);

    actingAs($owner)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Posts/Review')
            ->where('title', $publication->payload['title'])
            ->where('channel', $channel->name)
            ->has('project.name')
            ->missing('credentials'));
    actingAs(User::factory()->create())->get($url)->assertNotFound();

    $publication->update(['status' => PublicationStatus::PUBLISHED]);

    actingAs($owner)->get($url)->assertNotFound();
});
