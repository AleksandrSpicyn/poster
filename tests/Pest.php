<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Knuckles\Scribe\ScribeServiceProvider;
use Tests\TestCase;

use function Pest\Laravel\withoutVite;

require_once __DIR__ . '/Support/Console.php';
require_once __DIR__ . '/Support/Codex.php';
require_once __DIR__ . '/Support/Google.php';
require_once __DIR__ . '/Support/Projects.php';
require_once __DIR__ . '/Support/SeriesOperators.php';
require_once __DIR__ . '/Support/Series.php';
require_once __DIR__ . '/Support/EpisodeHttp.php';
require_once __DIR__ . '/Support/EpisodePublications.php';

pest()
    ->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

pest()
    ->beforeEach(function (): void {
        withoutVite();
        Http::preventStrayRequests();
    })
    ->in('Feature');

// Scribe caches the translation loader across Laravel application instances.
pest()
    ->afterEach(function (): void {
        ScribeServiceProvider::$customTranslationLayerLoaded = false;
    })
    ->in('Feature/Routes/DocumentationTest.php', 'Feature/Support/Documentation/ScribeMcpTest.php');
