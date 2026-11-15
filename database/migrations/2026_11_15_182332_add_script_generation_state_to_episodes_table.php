<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episodes', static function (Blueprint $table): void {
            $table->uuid('script_request_key')->nullable();
            $table->foreignId('script_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('script_generation_status')->nullable()->index();
            $table->json('script_request_payload')->nullable();
            $table->text('script_generation_error')->nullable();
            $table->timestamp('script_started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('episodes', static function (Blueprint $table): void {
            $table->dropForeign(['script_requested_by']);
            $table->dropColumn(['script_request_key', 'script_requested_by', 'script_generation_status', 'script_request_payload', 'script_generation_error', 'script_started_at']);
        });
    }
};
