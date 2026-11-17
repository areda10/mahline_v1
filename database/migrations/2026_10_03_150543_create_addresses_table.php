<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->ulidPrimary();
            $table->string('number', 50)->nullable();
            $table->string('street');
            $table->string('floor', 50)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 120);
            $table->string('region', 120)->nullable();

            $table->foreignUlid('country_id')
                ->constrained('countries')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignUlid('user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('deletion_source', 24)->nullable();
            $table->ulid('owner_deletion_batch_id')->nullable();

            $table->auditActorColumns();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'deleted_at'], 'addresses_user_active_idx');
            $table->index(['city', 'region']);
            $table->index('country_id');
            $table->index('owner_deletion_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
