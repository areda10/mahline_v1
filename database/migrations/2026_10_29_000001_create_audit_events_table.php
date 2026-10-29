<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('event_key', 120);
            $table->string('action', 80);

            // Polymorphic identifiers avoid coupling Audit to Identity or another domain.
            $table->string('actor_type', 120)->nullable();
            $table->char('actor_id', 26)->nullable();
            $table->char('cooperative_id', 26)->nullable();
            $table->string('subject_type', 160)->nullable();
            $table->string('subject_id', 64)->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();

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
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
