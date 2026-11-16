<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('миграция сохраняет IDs токены историю и старую связь', function (): void {
    withLegacyChannelSchema(function (Closure $up, Closure $down): void {
        $up();

        expect(DB::table('channel_project')->where('channel_id', 14)->where('project_id', 1)->exists())->toBeTrue();
        expect(DB::table('channels')->where('id', 14)->value('client_secret'))->toBe('saved-secret');

        $credentials = DB::table('channels')->where('id', 14)->value('credentials');

        if (! is_string($credentials)) {
            throw new RuntimeException('Expected saved JSON credentials.');
        }

        expect(json_decode($credentials, true))->toBe(['access_token' => 'saved-token']);
        expect((bool) DB::table('channels')->where('id', 14)->value('is_active'))->toBeTrue();
        expect(DB::table('posts')->where('id', 7)->value('channel_id'))->toBe(14);

        DB::table('channel_project')->insert(['channel_id' => 14, 'project_id' => 2]);
        DB::table('projects')->where('id', 1)->delete();

        expect(DB::table('channels')->where('id', 14)->exists())->toBeTrue();
        expect(DB::table('channels')->where('id', 14)->value('project_id'))->toBeNull();
        expect(DB::table('channel_project')->where('channel_id', 14)->value('project_id'))->toBe(2);
        expect(DB::table('posts')->where('id', 7)->exists())->toBeTrue();
    });
});

test('rollback восстанавливает старую схему без потери данных', function (): void {
    withLegacyChannelSchema(function (Closure $up, Closure $down): void {
        $up();
        $down();

        expect(Schema::hasTable('channel_project'))->toBeFalse();
        expect(DB::table('channels')->where('id', 14)->value('project_id'))->toBe(1);
        expect(DB::table('channels')->where('id', 14)->value('client_secret'))->toBe('saved-secret');
        expect(DB::table('posts')->where('id', 7)->exists())->toBeTrue();
    });
});

test('rollback отказывается терять новые общие связи', function (): void {
    withLegacyChannelSchema(function (Closure $up, Closure $down): void {
        $up();
        DB::table('channel_project')->insert(['channel_id' => 14, 'project_id' => 2]);

        expect($down)->toThrow(RuntimeException::class);

        expect(DB::table('channel_project')->count())->toBe(2);
        expect(Schema::hasColumn('channels', 'is_active'))->toBeTrue();
    });
});

/** @param Closure(Closure(): mixed, Closure(): mixed): void $test */
function withLegacyChannelSchema(Closure $test): void
{
    $original = DB::getDefaultConnection();
    $connection = config()->array('database.connections.' . $original);
    config(['database.connections.channel_migration_test' => [...$connection, 'prefix' => 'channel_migration_']]);
    DB::setDefaultConnection('channel_migration_test');

    try {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
        });
        $legacy = require database_path('migrations/2025_05_15_124159_create_channels_table.php');

        if (! $legacy instanceof Migration || ! method_exists($legacy, 'up')) {
            throw new RuntimeException('Invalid legacy migration.');
        }

        $legacy->up();
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
        });
        DB::table('projects')->insert([['id' => 1], ['id' => 2]]);
        DB::table('channels')->insert(['id' => 14, 'project_id' => 1, 'name' => 'Existing', 'type' => 'youtube', 'client_secret' => 'saved-secret', 'credentials' => json_encode(['access_token' => 'saved-token'], JSON_THROW_ON_ERROR)]);
        DB::table('posts')->insert(['id' => 7, 'channel_id' => 14]);
        $migration = require database_path('migrations/2026_11_16_110733_share_channels_between_projects.php');

        if (! $migration instanceof Migration || ! method_exists($migration, 'up') || ! method_exists($migration, 'down')) {
            throw new RuntimeException('Invalid shared channel migration.');
        }

        $test($migration->up(...), $migration->down(...));
    } finally {
        Schema::dropIfExists('posts');
        Schema::dropIfExists('channel_project');
        Schema::dropIfExists('channels');
        Schema::dropIfExists('projects');
        DB::purge('channel_migration_test');
        DB::setDefaultConnection($original);
    }
}
