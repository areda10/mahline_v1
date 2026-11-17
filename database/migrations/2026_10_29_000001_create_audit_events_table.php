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
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('event_key', 120);
            $table->string('action', 80);

            $table->string('actor_type', 120)->nullable();
            $table->char('actor_id', 26)->nullable();
            $table->char('cooperative_id', 26)->nullable();
            $table->string('subject_type', 160)->nullable();
            $table->string('subject_id', 64)->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();

            $table->char('previous_hash', 64)->nullable();
            $table->char('event_hash', 64)->unique('audit_events_event_hash_unique');

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('correlation_id', 100)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['event_key', 'occurred_at'], 'audit_events_event_occurred_idx');
            $table->index(['actor_type', 'actor_id', 'occurred_at'], 'audit_events_actor_occurred_idx');
            $table->index(['cooperative_id', 'occurred_at'], 'audit_events_coop_occurred_idx');
            $table->index(['subject_type', 'subject_id'], 'audit_events_subject_idx');
            $table->index('correlation_id');
        });

        Schema::create('audit_chain_heads', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->char('last_hash', 64)->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('audit_chain_heads')->insert([
            'id' => 1,
            'last_hash' => null,
            'updated_at' => now(),
        ]);

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared(
                "CREATE TRIGGER audit_events_prevent_update BEFORE UPDATE ON audit_events "
                ."FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit events are append-only'",
            );
            DB::unprepared(
                "CREATE TRIGGER audit_events_prevent_delete BEFORE DELETE ON audit_events "
                ."FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit events are append-only'",
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_events_prevent_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_events_prevent_delete');
        }

        Schema::dropIfExists('audit_chain_heads');
        Schema::dropIfExists('audit_events');
    }
};
