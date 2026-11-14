<?php

declare(strict_types=1);

use App\Events\AudioGenerated;
use App\Events\VideoGenerated;
use App\Jobs\CheckVideoCreation;
use App\Models\Post;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

test('неподтверждённый email закрывает кабинет и рабочие списки', function (string $name): void {
    actingAs(User::factory()->unverified()->create())->get(route($name))->assertRedirect(route('verification.notice'));
    getJson(route($name))->assertForbidden();
})->with(['dashboard', 'posts', 'prompts']);

test(
    'повторная проверка email отправляется только неподтверждённому пользователю',
    /** @throws Exception */
    function (): void {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        actingAs($user)->post(route('verification.send'))->assertRedirect()->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    },
);

test('шестая неудачная попытка входа блокирует даже верный пароль', function (): void {
    Event::fake([Lockout::class]);
    $user = User::factory()->create();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        postJson(route('login'), ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
    }

    postJson(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    assertGuest();
    Event::assertDispatched(Lockout::class);
});

test('недокументированный столбец сортировки не попадает в SQL', function (string $name): void {
    actingAs(User::factory()->create())->get(route($name, ['orderBy' => 'unknown_column']))->assertOk();
})->with(['posts', 'prompts']);

test('явный null размера страницы сохраняет документированный default', function (): void {
    actingAs(User::factory()->create())->get(route('prompts', ['perPage' => '']))->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->where('prompts.meta.per_page', 10));
});

test('удалённый канал не ломает историю публикаций', function (): void {
    $post = Post::factory()->create();
    $post->channel()->delete();

    actingAs(User::factory()->create())->get(route('posts'))->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->where('posts.data.0.id', $post->id)->where('posts.data.0.channel', null));
});

test('повторный callback аудио не запускает сборку снова', function (): void {
    Event::fake([AudioGenerated::class]);
    Prompt::factory()->create(['audio_id' => 'task-1', 'audio_url' => 'https://cdn.example.com/audio.mp3']);

    postJson(route('webhook.suno'), sunoPayload('task-1'))->assertOk();

    Event::assertNotDispatched(AudioGenerated::class);
});

test('пустой список треков отклоняется без сохранения', function (): void {
    $payload = sunoPayload('task-1');
    $payload['data']['data'] = [];

    postJson(route('webhook.suno'), $payload)->assertUnprocessable()->assertJsonValidationErrors('data.data');
});

test('повторная проверка готового видео не обращается к провайдеру', function (): void {
    Event::fake([VideoGenerated::class]);
    $prompt = Prompt::factory()->create(['video_id' => 'video-1', 'video_url' => 'https://cdn.example.com/video.mp4']);
    Http::fake();

    app()->call([new CheckVideoCreation($prompt), 'handle']);

    Http::assertNothingSent();
    Event::assertNotDispatched(VideoGenerated::class);
});

test('статические публичные страницы возвращают нужный компонент', function (string $name, string $component): void {
    get(route($name))->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->component($component));
})->with([['policy', 'Policy'], ['terms', 'Terms']]);

test('UKassa подтверждает callback и журналирует полученные данные', function (): void {
    $log = Log::spy();
    postJson(route('webhook.ukassa'), ['event' => 'payment.succeeded'])->assertOk()->assertContent('');
    $log->shouldHaveReceived('info')->once()
        ->with('Webhook UKassa received', ['event' => 'payment.succeeded']);
});
