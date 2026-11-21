<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_security_reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('cooperative_id');
            $table->ulid('cooperative_order_id')->nullable();
            $table->ulid('reporter_id');
            $table->string('reporter_role', 40);
            $table->string('category', 60);
            $table->text('description');
            $table->string('urgency', 16)->default('normal');
            $table->string('status', 24)->default('open');
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->ulid('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('closure_reason', 500)->nullable();
            $table->ulid('reopened_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->string('reopen_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['cooperative_id', 'status', 'created_at'], 'inventory_security_report_coop_status_idx');
            $table->index(['status', 'response_due_at', 'escalated_at'], 'inventory_security_report_due_idx');
            $table->index(['reporter_id', 'created_at'], 'inventory_security_report_reporter_idx');
            $table->index('cooperative_order_id', 'inventory_security_report_order_idx');
        });

        Schema::create('inventory_security_report_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('report_id')
                ->constrained('inventory_security_reports')
                ->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('old_status', 24)->nullable();
            $table->string('new_status', 24)->nullable();
            $table->string('reason', 500)->nullable();
            $table->ulid('actor_id')->nullable();
            $table->string('actor_type', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['report_id', 'occurred_at'], 'inventory_security_report_events_date_idx');
        });

        Schema::create('inventory_security_report_attachments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('report_id')
                ->constrained('inventory_security_reports')
                ->restrictOnDelete();
            $table->ulid('media_id');
            $table->ulid('uploaded_by');
            $table->timestamp('attached_at');
            $table->timestamps();

            $table->unique(['report_id', 'media_id'], 'inventory_security_report_attachment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_security_report_attachments');
        Schema::dropIfExists('inventory_security_report_events');
        Schema::dropIfExists('inventory_security_reports');
    }
};
