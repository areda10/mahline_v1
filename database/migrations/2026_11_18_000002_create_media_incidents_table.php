<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_incidents', function (Blueprint $table): void {
            $table->ulidPrimary();
            $table->string('reference', 32)->unique();
            $table->string('title', 160);
            $table->text('description');
            $table->string('status', 24)->default('OPEN');
            $table->char('reported_by', 26);
            $table->char('assigned_to', 26)->nullable();
            $table->text('resolution')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->json('metadata')->nullable();
            $table->auditActorColumns();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'media_incidents_status_created_index');
            $table->index(['reported_by', 'created_at'], 'media_incidents_reporter_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_incidents');
    }
};
