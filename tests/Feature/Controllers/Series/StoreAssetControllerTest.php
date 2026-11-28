<?php

declare(strict_types=1);

use App\Models\Series;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialOperator;
use function Tests\Support\serialVoiceWav;

test('сохраняет материал и возвращает формат галерее', function (string $kind, string $extension, string $format): void {
    Storage::fake('local');
    $user = serialOperator();
    $series = Series::factory()->recycle($user)->create();
    $contents = match ($extension) {
        'mp4' => File::get(base_path('tests/Fixtures/shot.mp4')),
        'webm' => Process::run(['ffmpeg', '-v', 'error', '-i', base_path('tests/Fixtures/shot.mp4'), '-an', '-c:v', 'libvpx', '-f', 'webm', 'pipe:1'])->throw()->output(),
        'png' => File::get(base_path('tests/Fixtures/portrait.png')),
        'wav' => serialVoiceWav(),
        'json' => '{"format":"poster.season.v1","episodes":[{"title":"Начало"}]}',
        default => throw new InvalidArgumentException($extension),
    };
    $upload = UploadedFile::fake()->createWithContent('sample.' . $extension, $contents);
    $file = new UploadedFile($upload->getPathname(), $upload->name, test: true);

    actingAs($user)
        ->post(route('series.assets.store', $series), ['kind' => $kind, 'label' => 'Пример', 'file' => $file])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $media = $series->getMedia(Series::ASSETS)->sole();
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    get(route('series.show', ['project' => $series->project_id, 'series' => $series, 'section' => 'materials']))
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('series.assets.0.format', $format)
            ->where('series.assets.0.kind', $kind)
            ->where('series.assets.0.extension', $extension)
            ->where('series.assets.0.size', $media->size));
})->with([['insert', 'mp4', 'video'], ['insert', 'webm', 'video'], ['insert', 'png', 'image'], ['sound', 'wav', 'audio'], ['season', 'json', 'json']]);

test('отклоняет несовместимый материал', function (string $kind, string $filename, string $source): void {
    Storage::fake('local');
    $user = serialOperator();
    $series = Series::factory()->recycle($user)->create();
    $file = UploadedFile::fake()->createWithContent($filename, $source === 'video' ? File::get(base_path('tests/Fixtures/shot.mp4')) : $source);

    actingAs($user)
        ->post(route('series.assets.store', $series), ['kind' => $kind, 'label' => 'Пример', 'file' => $file])
        ->assertSessionHasErrors('file');

    expect($series->getMedia(Series::ASSETS))->toBeEmpty();
})->with([['location', 'location.png', 'video'], ['insert', 'clip.mp4', 'fake mp4'], ['season', 'season.json', '{"episodes":[]}']]);

test('архивный сериал запрещает загрузку', function (): void {
    Storage::fake('local');
    $user = serialOperator();
    $series = Series::factory()->recycle($user)->create(['archived_at' => now()]);
    $file = UploadedFile::fake()->createWithContent('clip.mp4', File::get(base_path('tests/Fixtures/shot.mp4')));

    actingAs($user)
        ->post(route('series.assets.store', $series), ['kind' => 'insert', 'label' => 'Архив', 'file' => $file])
        ->assertSessionHasErrors('file');

    expect($series->getMedia(Series::ASSETS))->toBeEmpty();
});
