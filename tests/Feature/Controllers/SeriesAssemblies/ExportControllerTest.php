<?php

declare(strict_types=1);

use App\Actions\Series\BuildSeriesAssemblyAction;
use App\Data\Series\AcceptSeriesVideoData;
use App\Models\SeriesAssembly;
use App\Support\WorkspaceOwner;
use App\Tasks\Series\AcceptSeriesAssembly;
use App\Tasks\Series\SaveSeriesAssembly;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\get;
use function Tests\Support\markSeriesBoundariesReviewed;
use function Tests\Support\singleVideoFixture;

test('подписанный экспорт доступен только для принятого неизменённого файла', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $assembly = app(SaveSeriesAssembly::class)->run($context['series']);
    app(BuildSeriesAssemblyAction::class)->run($assembly->id);
    $assembly->refresh();
    $media = $assembly->getFirstMedia(SeriesAssembly::VIDEO) ?? throw new LogicException('No media');
    $url = URL::temporarySignedRoute('series_assemblies.export', now()->addHour(), ['assembly' => $assembly->id, 'media' => $media->id, 'owner' => WorkspaceOwner::id($assembly)]);

    get($url)->assertNotFound();
    markSeriesBoundariesReviewed($assembly);
    app(AcceptSeriesAssembly::class)->run($assembly, new AcceptSeriesVideoData($assembly->lock_version, true, 'All seams reviewed.'), $context['user']);
    get($url)->assertOk();
    $parameters = [
        'assembly' => $assembly->id,
        'media' => $media->id,
        'owner' => WorkspaceOwner::id($assembly),
    ];
    $signed = fn(string $checksum): string => URL::temporarySignedRoute('series_assemblies.export', now()->addHour(), [...$parameters, 'checksum' => $checksum]);
    get($signed(hash_file('sha256', $media->getPath()) ?: throw new LogicException('No checksum')))->assertOk();
    get($signed(str_repeat('0', 64)))->assertNotFound();
    get(route('series_assemblies.export', ['assembly' => $assembly, 'media' => $media, 'owner' => WorkspaceOwner::id($assembly)]))->assertForbidden();
    file_put_contents($media->getPath(), 'Changed file');
    get($url)->assertNotFound();
});
