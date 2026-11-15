<?php

declare(strict_types=1);

use App\Enums\VideoProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('prompts')
            ->where('video_provider', VideoProvider::SEEDANCE->value)
            ->whereNotNull('video_url')
            ->whereNull('audio_url')
            ->update(['audio_url' => DB::raw('video_url')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Нормализованные ссылки совместимы и с предыдущей версией конвейера.
    }
};
