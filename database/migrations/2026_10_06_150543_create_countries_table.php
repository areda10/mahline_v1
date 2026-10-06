<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->ulidPrimary();

            $table->string('code', 2);

            $table->string('iso3', 3);

            $table->string('name');

            $table->string('native_name');

            $table->string('phone_code', 10);

            $table->string('currency', 3);

            $table->string('locale', 10);

            $table->boolean('is_active')->default(true);

            $table->auditActorColumns();

            $table->timestamps();

            $table->softDeletes();

            $table->unique('code');

            $table->unique('iso3');

            $table->index('name');

            $table->index('currency');

            $table->index('locale');

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};