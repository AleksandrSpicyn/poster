<?php

declare(strict_types=1);

use App\Models\Prompt;
use Illuminate\Support\Facades\Storage;

use function Tests\Support\consoleCommand;

test('переносит прежний файл без смены ID и сохраняет производные файлы', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $prompt = Prompt::factory()->create();
    $media = $prompt->addMedia(base_path('tests/Fixtures/shot.mp4'))->preservingOriginal()->toMediaCollection('legacy', 'public');
    Storage::disk('public')->put($media->id . '/conversions/preview.jpg', 'test');
    $hash = hash_file('sha256', $media->getPath());
    $relative = $media->getPathRelativeToRoot();

    consoleCommand('workspace:private-media')->assertSuccessful();
    Storage::disk('public')->assertExists($relative);
    consoleCommand('workspace:private-media', ['--apply' => true])->assertSuccessful();
    consoleCommand('workspace:private-media', ['--apply' => true])->assertSuccessful();

    expect($media->refresh()->disk)->toBe('local')->and(hash_file('sha256', $media->getPath()))->toBe($hash);
    Storage::disk('public')->assertMissing($relative);
    Storage::disk('local')->assertExists($media->id . '/conversions/preview.jpg');
});
