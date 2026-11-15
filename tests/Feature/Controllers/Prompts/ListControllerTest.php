<?php

declare(strict_types=1);

use App\Models\Prompt;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('список идей возвращает страницы и данные проекта', function (): void {
    $first = Prompt::factory()->create(['created_at' => now()->subDay()]);
    $last = Prompt::factory()->create();

    actingAs(User::factory()->create())
        ->get(route('prompts', ['sort' => 'desc', 'perPage' => 1]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Prompts')
            ->has('prompts.data', 1)
            ->where('prompts.data.0.id', $last->id)
            ->where('prompts.data.0.project', $last->project?->name)
            ->where('prompts.meta.total', 2));
    get(route('prompts', ['sort' => 'desc', 'perPage' => 1, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->where('prompts.data.0.id', $first->id));
});

test('явный null размера страницы сохраняет документированный default', function (): void {
    actingAs(User::factory()->create())
        ->get(route('prompts', ['perPage' => '']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->where('prompts.meta.per_page', 10));
});

test('неподтверждённый email закрывает кабинет и рабочие списки', function (): void {
    actingAs(User::factory()
        ->unverified()
        ->create())
        ->get(route('prompts'))
        ->assertRedirect(route('verification.notice'));
    getJson(route('prompts'))->assertForbidden();
});

test('гость не видит рабочие списки', function (): void {
    get(route('prompts'))->assertRedirect(route('login'));
    getJson(route('prompts'))->assertUnauthorized();
});

test('недокументированный столбец сортировки не попадает в SQL', function (): void {
    actingAs(User::factory()->create())
        ->get(route('prompts', ['orderBy' => 'unknown_column']))
        ->assertOk();
});

test('списки отклоняют некорректные фильтры', function (array $input, string $field): void {
    actingAs(User::factory()->create())
        ->getJson(route('prompts', $input))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'размер страницы' => [['perPage' => 0], 'perPage'],
    'порядок' => [['sort' => 'invalid'], 'sort'],
    'нулевая страница' => [['page' => 0], 'page'],
    'дробная страница' => [['page' => '1.5'], 'page'],
    'дробный размер' => [['perPage' => '1.5'], 'perPage'],
]);

test('пустые фильтры сохраняют размер страницы и порядок по умолчанию', function (): void {
    Prompt::factory()->create(['created_at' => now()->subDay()]);
    $last = Prompt::factory()->create();

    actingAs(User::factory()->create())
        ->get(route('prompts', ['sort' => '', 'perPage' => '', 'page' => '']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('prompts.data.0.id', $last->id)
            ->where('prompts.meta.per_page', 10)
            ->where('prompts.meta.current_page', 1)
            ->where('filters.sort', null));
});

test('отсутствующие фильтры не добавляются в ответ', function (): void {
    actingAs(User::factory()->create())
        ->get(route('prompts'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->where('filters', []));
});

test('список передаёт варианты сортировки из enum', function (): void {
    actingAs(User::factory()->create())
        ->get(route('prompts'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('sortDirections', ['desc' => 'Newest', 'asc' => 'Oldest'])
            ->where('defaultSort', 'desc'));
});

test('идеи сортируются от старых к новым', function (): void {
    $first = Prompt::factory()->create(['created_at' => now()->subDay()]);
    $last = Prompt::factory()->create();

    actingAs(User::factory()->create())
        ->get(route('prompts', ['sort' => 'asc']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('prompts.data.0.id', $first->id)
            ->where('prompts.data.1.id', $last->id)
            ->where('filters.sort', 'asc'));
});

test('список показывает этап по сохранённым результатам', function (array $attributes, string $stage, string $label): void {
    /** @var array<string, bool|string> $attributes */
    $prompt = Prompt::factory()
        ->create($attributes);

    actingAs(User::factory()
        ->create());

    get(route('prompts'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('prompts.data.0.id', $prompt->id)
            ->where('prompts.data.0.stage', $stage)
            ->where('prompts.data.0.stage_label', $label));
})->with([
    'ожидается изображение' => [[], 'image', 'Awaiting image'],
    'изображение готово' => [['image_url' => 'https://example.test/image.png'], 'starting_media', 'Starting media'],
    'видео запущено' => [['video_id' => 'video-task'], 'media', 'Awaiting video & audio'],
    'аудио запущено' => [['audio_id' => 'audio-task'], 'media', 'Awaiting video & audio'],
    'ожидается видео' => [['audio_url' => 'https://example.test/audio.mp3'], 'video', 'Awaiting video'],
    'ожидается аудио' => [['video_url' => 'https://example.test/video.mp4'], 'audio', 'Awaiting audio'],
    'ожидается сборка' => [['video_url' => 'https://example.test/video.mp4', 'audio_url' => 'https://example.test/audio.mp3'], 'assembly', 'Awaiting assembly'],
    'сборка завершена' => [['generated' => true], 'ready', 'Video ready'],
]);
