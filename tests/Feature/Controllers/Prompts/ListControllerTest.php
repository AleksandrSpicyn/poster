<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Prompt;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Tests\Support\workspaceOwner;

test('список идей возвращает страницы и данные проекта', function (): void {
    $first = Prompt::factory()->recycle(workspaceOwner())->create(['created_at' => now()->subDay()]);
    $last = Prompt::factory()->recycle(workspaceOwner())->create();

    actingAs(workspaceOwner())
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

test('обычный список не раскрывает приватные сериальные дубли', function (): void {
    $standalone = Prompt::factory()->recycle(workspaceOwner())->create();
    Prompt::factory()->recycle(workspaceOwner())->forShot(Shot::factory()->create())->create(['prompt' => 'SERIAL_PRIVATE_SENTINEL']);

    actingAs(workspaceOwner())
        ->get(route('prompts'))
        ->assertOk()
        ->assertDontSee('SERIAL_PRIVATE_SENTINEL')
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('prompts.meta.total', 1)
            ->has('prompts.data', 1)
            ->where('prompts.data.0.id', $standalone->id));
});

test('явный null размера страницы сохраняет документированный default', function (): void {
    actingAs(workspaceOwner())
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
    actingAs(workspaceOwner())
        ->get(route('prompts', ['orderBy' => 'unknown_column']))
        ->assertOk();
});

test('списки отклоняют некорректные фильтры', function (array $input, string $field): void {
    actingAs(workspaceOwner())
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
    Prompt::factory()->recycle(workspaceOwner())->create(['created_at' => now()->subDay()]);
    $last = Prompt::factory()->recycle(workspaceOwner())->create();

    actingAs(workspaceOwner())
        ->get(route('prompts', ['sort' => '', 'perPage' => '', 'page' => '']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('prompts.data.0.id', $last->id)
            ->where('prompts.meta.per_page', 10)
            ->where('prompts.meta.current_page', 1)
            ->where('filters.sort', null));
});

test('отсутствующие фильтры не добавляются в ответ', function (): void {
    actingAs(workspaceOwner())
        ->get(route('prompts'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->where('filters', []));
});

test('список передаёт варианты сортировки из enum', function (): void {
    actingAs(workspaceOwner())
        ->get(route('prompts'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('sortDirections', ['desc' => 'Newest', 'asc' => 'Oldest'])
            ->where('defaultSort', 'desc'));
});

test('идеи сортируются от старых к новым', function (): void {
    $first = Prompt::factory()->recycle(workspaceOwner())->create(['created_at' => now()->subDay()]);
    $last = Prompt::factory()->recycle(workspaceOwner())->create();

    actingAs(workspaceOwner())
        ->get(route('prompts', ['sort' => 'asc']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('prompts.data.0.id', $first->id)
            ->where('prompts.data.1.id', $last->id)
            ->where('filters.sort', 'asc'));
});

test('список показывает этап по сохранённым результатам', function (array $attributes, string $stage, string $label): void {
    /** @var array<string, bool|string> $attributes */
    $prompt = Prompt::factory()->recycle(workspaceOwner())
        ->create($attributes);

    actingAs(workspaceOwner());

    get(route('prompts'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('prompts.data.0.id', $prompt->id)
            ->where('prompts.data.0.stage', $stage)
            ->where('prompts.data.0.stage_label', $label));
})->with([
    'ожидается изображение' => [[], 'image', 'Ожидание кадра'],
    'изображение готово' => [['image_url' => 'https://example.test/image.png'], 'starting_media', 'Подготовка видео и звука'],
    'видео запущено' => [['video_id' => 'video-task'], 'media', 'Ожидание видео и звука'],
    'аудио запущено' => [['audio_id' => 'audio-task'], 'media', 'Ожидание видео и звука'],
    'ожидается видео' => [['audio_url' => 'https://example.test/audio.mp3'], 'video', 'Ожидание видео'],
    'ожидается аудио' => [['video_url' => 'https://example.test/video.mp4'], 'audio', 'Ожидание звука'],
    'генерируется Seedance' => [['video_provider' => 'seedance', 'video_id' => 'seedance-task'], 'media', 'Ожидание видео и звука'],
    'Seedance готов к сборке без Suno' => [['video_provider' => 'seedance', 'video_url' => 'https://example.test/native.mp4', 'audio_url' => 'https://example.test/native.mp4'], 'assembly', 'Сборка'],
    'ожидается сборка' => [['video_url' => 'https://example.test/video.mp4', 'audio_url' => 'https://example.test/audio.mp3'], 'assembly', 'Сборка'],
    'сборка завершена' => [['generated' => true], 'ready', 'Видео готово'],
]);

test('фильтр генераций использует только проекты владельца', function (): void {
    $owner = workspaceOwner();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $other = Project::factory()->create();
    $take = Prompt::factory()->for($project)->create();
    Prompt::factory()->for($other)->create();
    Prompt::factory()->recycle($owner)->create();

    actingAs($owner)
        ->get(route('prompts', ['project' => $project->id]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('prompts.meta.total', 1)
            ->where('prompts.data.0.id', $take->id)
            ->where('filters.project', $project->id)
            ->where('projects', fn(mixed $projects): bool => $projects instanceof Collection && $projects->contains('id', $project->id) && ! $projects->contains('id', $other->id)));

    get(route('prompts', ['project' => $other->id]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->where('prompts.meta.total', 0));
});
