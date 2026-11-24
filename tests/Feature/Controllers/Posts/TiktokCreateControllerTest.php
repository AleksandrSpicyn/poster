<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Post;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

test('владелец видит только доступные режимы своего TikTok', function (bool $direct, bool $inbox): void {
    config(['services.tiktok.direct_post' => true]);
    $owner = User::factory()->create();
    $channel = Channel::factory()->recycle($owner)->create(['type' => ChannelType::TIKTOK, 'credentials' => ['channel_id' => 'alpha',
        'account_name' => 'Alpha', 'scope' => implode(',', array_filter([$direct ? 'video.publish' : null, $inbox ? 'video.upload' : null])),
        'access_token' => 'private-token', 'expires_at' => now()->addHour()->toIso8601String()]]);
    Http::fake(['*/creator_info/query/' => Http::response(tiktokCreatorResponse())]);

    actingAs(User::factory()->create())->get(route('posts.tiktok.create', $channel))->assertNotFound();
    Http::assertNothingSent();

    $response = actingAs($owner)->get(route('posts.tiktok.create', $channel));

    if (! $direct && ! $inbox) {
        $response->assertForbidden();

        return;
    }

    $response->assertOk()->assertDontSee('private-token')->assertInertia(fn(Assert $page): Assert => $page->component('Posts/Tiktok')
        ->where('account', 'Alpha')->where('inbox', $inbox)->has('videos.data', 0)
        ->where('creator', fn($value): bool => ($value !== null) === $direct));
})->with([[false, true], [true, false], [false, false]]);

test('ошибка проверки прямой публикации сохраняет только полученные способы доставки', function (bool $inbox): void {
    config(['services.tiktok.direct_post' => true]);
    $owner = User::factory()->create();
    $channel = Channel::factory()->recycle($owner)->create([
        'type' => ChannelType::TIKTOK,
        'credentials' => [
            'channel_id' => 'alpha',
            'scope' => $inbox ? 'video.publish,video.upload' : 'video.publish',
            'access_token' => 'private-token',
            'expires_at' => now()->addHour()->toIso8601String(),
        ],
    ]);
    Http::fakeSequence('*/creator_info/query/')
        ->pushStatus(503)
        ->push(tiktokCreatorResponse())
        ->push(tiktokCreatorResponse());

    actingAs($owner)
        ->get(route('posts.tiktok.create', $channel))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('inbox', $inbox)
            ->where('creator', null)
            ->where('directUnavailable', true));

    actingAs($owner)
        ->get(route('posts.tiktok.create', $channel))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('inbox', $inbox)
            ->where('creator.nickname', 'Alpha')
            ->where('directUnavailable', false)
            ->reloadOnly('videos', fn(Assert $updated): Assert => $updated->has('videos.data', 0)->missing('creator')));

    Http::assertSentCount(2);
})->with([true, false]);

test('видео показывает квитанцию выбранного канала владельца', function (?string $publishedAccount, bool $currentRecipient): void {
    Storage::fake('local');
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $channel = Channel::factory()->for($project)->create(['type' => ChannelType::TIKTOK, 'credentials' => ['channel_id' => 'qa', 'scope' => 'video.upload', 'access_token' => 'fake-access']]);
    $other = Channel::factory()->for($project)->create();
    $prompt = Prompt::factory()->for($project)->create(['generated' => true]);
    $media = $prompt->addMedia(base_path('tests/Fixtures/shot.mp4'))->preservingOriginal()->toMediaCollection('default', 'local');
    $receipt = PublicationRequest::create(['prompt_id' => $prompt->id, 'channel_id' => $channel->id, 'created_by' => $owner->id,
        'status' => 'published', 'payload' => ['tiktok' => ['account_id' => $publishedAccount]], 'published_at' => now()]);
    Post::factory()->for($channel)->create(['publication_request_id' => $receipt->id, 'url' => 'https://example.test/confirmed']);
    PublicationRequest::create(['prompt_id' => $prompt->id, 'channel_id' => $other->id, 'created_by' => $owner->id, 'status' => 'inbox', 'payload' => []]);

    actingAs($owner)
        ->get(route('posts.tiktok.create', $channel))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('videos.data.0.id', $media->id)
            ->where('videos.data.0.project.id', $project->id)
            ->where('videos.data.0.assembly_number', null)
            ->where('videos.data.0.receipt.id', $receipt->id)
            ->where('videos.data.0.receipt.status', 'published')
            ->where('videos.data.0.receipt.current_recipient', $currentRecipient)
            ->where('videos.data.0.receipt.url', 'https://example.test/confirmed'));
})->with([
    'текущий аккаунт' => ['qa', true],
    'переподключённый аккаунт' => ['previous', false],
    'без сохранённого аккаунта' => [null, false],
]);
