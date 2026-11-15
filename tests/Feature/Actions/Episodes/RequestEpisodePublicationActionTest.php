<?php

declare(strict_types=1);

use App\Actions\Episodes\RequestEpisodePublicationAction;
use App\Data\Episodes\PublishEpisodeData;
use App\Jobs\PublishEpisodeJob;
use App\Models\Channel;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Tests\Support\acceptedEpisodePublication;

test('запрос публикации сохраняет один receipt и не отправляет ролик', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class]);
    Http::fake();
    $data = new PublishEpisodeData([$channel->id]);

    app(RequestEpisodePublicationAction::class)->run($assembly, $data, $user);
    app(RequestEpisodePublicationAction::class)->run($assembly, $data, $user);

    expect(PublicationRequest::query()->count())->toBe(1);
    expect(PublicationRequest::query()->firstOrFail()->payload['title'])->toContain('The locked door');
    Queue::assertPushed(PublishEpisodeJob::class, 1);
    Http::assertNothingSent();
});

test('канал другого проекта не получает запрос публикации', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'user' => $user] = acceptedEpisodePublication();
    $foreign = Channel::factory()->create();
    Queue::fake([PublishEpisodeJob::class]);

    expect(fn() => app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$foreign->id]), $user))
        ->toThrow(ValidationException::class);

    expect(PublicationRequest::query()->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

test('отозванное подтверждение email блокирует публикацию', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    $user->forceFill(['email_verified_at' => null])->save();
    Queue::fake([PublishEpisodeJob::class]);

    expect(fn() => app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user))
        ->toThrow(AuthorizationException::class);

    expect(PublicationRequest::query()->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

test('подтверждённый посторонний не создаёт запрос публикации', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel] = acceptedEpisodePublication();
    $user = User::factory()->create();
    Queue::fake();
    Http::fake();

    expect(fn() => app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user))
        ->toThrow(AuthorizationException::class);

    expect(PublicationRequest::query()->exists())->toBeFalse();
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});
