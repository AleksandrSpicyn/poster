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
        Schema::table('clipping_connections', function (Blueprint $table): void {
            $table->boolean('import_projects')->default(true);
            $table->boolean('import_accounts')->default(true);
            $table->boolean('import_archives')->default(false);
            $table->unsignedBigInteger('scope_version')->default(0);
        });
        Schema::table('clipping_imports', function (Blueprint $table): void {
            $table->string('external_id')->nullable()->change();
            $table->unsignedBigInteger('scope_version')->default(0);
        });
    }

    public function down(): void
    {
        DB::table('clipping_imports')->whereNull('external_id')->update(['external_id' => '']);
        Schema::table('clipping_imports', function (Blueprint $table): void {
            $table->string('external_id')->nullable(false)->change();
            $table->dropColumn('scope_version');
        });
        Schema::table('clipping_connections', function (Blueprint $table): void {
            $table->dropColumn(['import_projects', 'import_accounts', 'import_archives', 'scope_version']);
        });
    }
};
