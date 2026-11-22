<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\OpusClip;
use App\Models\OpusSource;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('открывает отчёт подтверждённому пользователю и проверяет период', function (): void {
    get(route('dashboard.analytics'))->assertRedirect(route('login'));
    actingAs(User::factory()->unverified()->create());
    get(route('dashboard.analytics'))->assertRedirect(route('verification.notice'));

    actingAs(User::factory()->create());

    get(route('dashboard.analytics', ['days' => 30, 'platform' => 'instagram']))
        ->assertInertia(fn(Assert $page): Assert => $page->component('PublicationAnalytics')->where('analytics.filters.days', 30)
            ->where('analytics.filters.platform', 'instagram')->has('analytics.daily', 30)->has('analytics.publications', 0));
    get(route('dashboard.analytics', ['from' => '1900-01-01', 'to' => '2000-01-01']))
        ->assertInertia(fn(Assert $page): Assert => $page->has('analytics.daily', 2)->where('analytics.daily.1900-01-01', null)->where('analytics.daily.2000-01-01', null));
    getJson(route('dashboard.analytics', ['days' => 999]))->assertUnprocessable()->assertJsonValidationErrors('days');
});

test('считает изменение времени просмотра за выбранные даты и сохраняет пропуски', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $channel = Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE, 'external_account' => 'studio']);
    $clip = OpusClip::factory()->for(OpusSource::factory()->for($project), 'source')->create();
    $end = now('UTC')->startOfDay();
    $from = $end->copy()->subDays(2)->toDateString();

    foreach ([3 => ['views' => 100, 'estimatedMinutesWatched' => 1000], 1 => ['views' => 135], 0 => ['views' => 145, 'estimatedMinutesWatched' => 1030, 'shares' => 0]] as $ago => $values) {
        $clip->latestMetrics()->create([
            'platform' => 'youtube', 'media_id' => 'abcdefghijk', 'account_id' => 'studio',
            'observed_on' => $end->copy()->subDays($ago)->toDateString(), 'metrics' => $values,
        ]);
    }

    $period = ['from' => $from, 'to' => $end->toDateString(), 'project' => $project->id];
    actingAs($owner);

    get(route('dashboard.analytics', $period))
        ->assertInertia(fn(Assert $page): Assert => $page
            ->has('analytics.daily', 3)
            ->where('analytics.daily.' . $from, null)
            ->where('analytics.totals.views', 145)
            ->where('analytics.summaries.views', 45)
            ->where('analytics.totals.shares', 0)
            ->where('analytics.summaries.shares', null)
            ->where('analytics.publications.0.watchMinutesChange', 30)
            ->where('analytics.publications.0.channelName', $channel->name));

    get(route('dashboard.analytics', [...$period, 'exclude_channels' => [$channel->id]]))
        ->assertInertia(fn(Assert $page): Assert => $page->has('analytics.publications', 0)->has('analytics.coverage', 0));
    getJson(route('dashboard.analytics', ['from' => $end->toDateString(), 'to' => $from]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});
