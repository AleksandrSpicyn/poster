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
        Schema::create('generation_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id');
            $table->foreignId('order_id')->unique();
            $table->foreign(['owner_id', 'order_id'])->references(['owner_id', 'id'])->on('billing_orders');
            $table->string('entitlement_key');
            $table->unique(['owner_id', 'entitlement_key']);
            $table->unique(['owner_id', 'id']);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->index(['owner_id', 'ends_at']);
            $table->unsignedInteger('granted_seconds');
            $table->unsignedInteger('available_seconds');
            $table->unsignedInteger('held_seconds')->default(0);
            $table->unsignedInteger('consumed_seconds')->default(0);
            $table->unsignedInteger('expired_seconds')->default(0);
            $table->unsignedInteger('revoked_seconds')->default(0);
            $table->timestamps();
        });
        $this->protectGrantBalance();

        Schema::create('generation_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('billing_accounts', 'owner_id');
            $table->string('intent_key');
            $table->unique(['owner_id', 'intent_key']);
            $table->unique(['owner_id', 'id']);
            $table->char('request_hash', 64);
            $table->string('accounting_mode');
            $table->string('status');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('episode_revision_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prompt_id')->nullable()->constrained()->nullOnDelete();
            $table->json('quote_snapshot');
            $table->index(['status', 'updated_at']);
            $table->timestamps();
        });
        Schema::create('generation_reservation_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id');
            $table->foreignId('reservation_id');
            $table->foreignId('grant_id');
            $table->foreign(['owner_id', 'reservation_id'], 'part_owner_reservation_fk')->references(['owner_id', 'id'])->on('generation_reservations');
            $table->foreign(['owner_id', 'grant_id'], 'part_owner_grant_fk')->references(['owner_id', 'id'])->on('generation_grants');
            $table->unique(['grant_id', 'id']);
            $table->string('take_key');
            $table->unique(['reservation_id', 'take_key', 'grant_id'], 'part_allocation_unique');
            $table->foreignId('shot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prompt_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('seconds');
            $table->string('status');
            $table->index(['status', 'updated_at']);
            $table->timestamps();
        });
        Schema::create('generation_credit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id');
            $table->foreignId('grant_id');
            $table->foreign(['owner_id', 'grant_id'])->references(['owner_id', 'id'])->on('generation_grants');
            $table->foreignId('part_id')->nullable();
            $table->foreign(['grant_id', 'part_id'], 'entry_grant_part_fk')->references(['grant_id', 'id'])->on('generation_reservation_parts');
            $table->string('event_key')->unique();
            $table->string('kind');
            $table->string('reason')->nullable();

            foreach (['granted', 'available', 'held', 'consumed', 'expired', 'revoked'] as $counter) {
                $table->integer($counter . '_delta')->default(0);
            }

            $table->timestamp('created_at');
            $table->index(['owner_id', 'created_at', 'id']);
        });
    }

    private function protectGrantBalance(): void
    {
        $counters = ['available_seconds', 'held_seconds', 'consumed_seconds', 'expired_seconds', 'revoked_seconds'];

        if (DB::getDriverName() === 'sqlite') {
            $columns = array_map(static fn(string $name): string => 'NEW.' . $name, $counters);
            $invalid = 'NEW.granted_seconds != ' . implode(' + ', $columns) . ' OR min(' . implode(', ', $columns) . ') < 0';

            foreach (['insert', 'update'] as $event) {
                DB::statement("CREATE TRIGGER grant_balance_$event BEFORE $event ON generation_grants WHEN $invalid BEGIN SELECT RAISE(ABORT, 'Invalid grant balance'); END");
            }

            return;
        }

        DB::statement('ALTER TABLE generation_grants ADD CONSTRAINT grant_balance CHECK (granted_seconds = ' . implode(' + ', $counters) . ')');
    }

    public function down(): void
    {
        foreach (['generation_credit_entries', 'generation_reservation_parts', 'generation_reservations', 'generation_grants'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
