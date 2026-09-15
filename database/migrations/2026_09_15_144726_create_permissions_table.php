<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */

            $table->ulidPrimary();

            /*
            |--------------------------------------------------------------------------
            | Permission identity
            |--------------------------------------------------------------------------
            */

            $table->string('name', 100);

            $table->string('slug', 100)->unique();

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')->default(true);

            /*
            |--------------------------------------------------------------------------
            | Audit timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Audit actors
            |--------------------------------------------------------------------------
            */

            $table->auditActorColumns();

            /*
            |--------------------------------------------------------------------------
            | Soft deletion
            |--------------------------------------------------------------------------
            */

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};