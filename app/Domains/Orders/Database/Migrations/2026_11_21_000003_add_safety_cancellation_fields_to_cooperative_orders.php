<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cooperative_orders', function (Blueprint $table): void {
            $table->timestamp('safety_cancelled_at')->nullable();
            $table->string('safety_cancellation_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cooperative_orders', function (Blueprint $table): void {
            $table->dropColumn(['safety_cancelled_at', 'safety_cancellation_reason']);
        });
    }
};
