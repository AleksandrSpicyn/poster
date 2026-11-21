<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', static function (Blueprint $table): void {
            $table->foreignId('opus_source_id')->nullable()->unique()->constrained('opus_sources')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', static function (Blueprint $table): void {
            $table->dropForeign(['opus_source_id']);
            $table->dropUnique(['opus_source_id']);
            $table->dropColumn('opus_source_id');
        });
    }
};
