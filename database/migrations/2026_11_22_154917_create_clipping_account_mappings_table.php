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
        Schema::create('clipping_account_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clipping_connection_id')->constrained()->restrictOnDelete();
            $table->string('provider_account_id');
            $table->string('external_id');
            $table->string('platform', 32);
            $table->foreignId('channel_id')->constrained()->restrictOnDelete();
            $table->unique(['clipping_connection_id', 'provider_account_id'], 'clipping_account_identity');
            $table->timestamps();
        });
        Schema::table('clipping_imports', function (Blueprint $table): void {
            $table->json('accounts')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('clipping_account_mappings')->exists()) {
            throw new RuntimeException('Export clipping account mappings before rollback.');
        }

        Schema::table('clipping_imports', function (Blueprint $table): void {
            $table->dropColumn('accounts');
        });
        Schema::dropIfExists('clipping_account_mappings');
    }
};
