<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = ['owner_id', 'publication_key', 'platform', 'account', 'media_id'];
        $identities = DB::table('publication_observations')->select($columns)->distinct();

        $conflicts = DB::table('publication_observations')->select('owner_id', 'publication_key')->groupBy('owner_id', 'publication_key')
            ->havingRaw('count(distinct hex(platform)) > 1 or count(distinct hex(account)) > 1 or count(distinct hex(media_id)) > 1');

        if ($conflicts->exists()) {
            throw new RuntimeException('Conflicting historical publication identities require review.');
        }

        Schema::create('publication_metric_identities', static function (Blueprint $table): void {
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->char('publication_key', 64);
            $table->string('platform', 20);
            $table->string('account');
            $table->string('media_id', 120);
            $table->primary(['owner_id', 'publication_key']);
        });

        DB::table('publication_metric_identities')->insertUsing($columns, $identities);
        Schema::table('publication_observations', static function (Blueprint $table): void {
            $table->foreign(['owner_id', 'publication_key'])
                ->references(['owner_id', 'publication_key'])->on('publication_metric_identities')->cascadeOnDelete();
            $table->dropColumn(['platform', 'account', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::table('publication_observations', static function (Blueprint $table): void {
            $table->dropForeign(['owner_id', 'publication_key']);
            $table->string('platform', 20)->default('');
            $table->string('account')->default('');
            $table->string('media_id', 120)->default('');
        });

        $identity = DB::table('publication_metric_identities')->whereColumn('owner_id', 'publication_observations.owner_id')
            ->whereColumn('publication_key', 'publication_observations.publication_key');
        DB::table('publication_observations')->update([
            'platform' => (clone $identity)->select('platform'),
            'account' => (clone $identity)->select('account'),
            'media_id' => (clone $identity)->select('media_id'),
        ]);
        Schema::table('publication_observations', static function (Blueprint $table): void {
            $table->string('platform', 20)->change();
            $table->string('account')->change();
            $table->string('media_id', 120)->change();
        });
        Schema::dropIfExists('publication_metric_identities');
    }
};
