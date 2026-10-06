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

            $table->string('address_line_1');

            $table->string('address_line_2')->nullable();

            $table->string('postal_code')->nullable();

            $table->string('city');

            $table->string('state')->nullable();

            $table->ulid('country_id');

            $table->decimal('latitude', 10, 7)->nullable();

            $table->decimal('longitude', 10, 7)->nullable();

            $table->auditActorColumns();

            $table->timestamps();

            $table->softDeletes();

            $table->index('country_id');

            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};