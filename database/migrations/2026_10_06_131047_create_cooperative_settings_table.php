<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperative_settings', function (Blueprint $table): void {
            $table->ulidPrimary();

            $table->ulid('cooperative_id');

            $table->string('currency', 3)->default('MAD');

            $table->string('locale', 10)->default('fr');

            $table->string('timezone', 64)->default('Africa/Casablanca');

            $table->string('order_prefix', 20)->default('CMD');

            $table->string('invoice_prefix', 20)->default('FAC');

            $table->decimal('default_tax_rate', 5, 2)->default(0);

            $table->boolean('prices_include_tax')->default(false);

            $table->boolean('notify_new_order')->default(true);

            $table->boolean('notify_order_status')->default(true);

            $table->boolean('notify_low_stock')->default(true);

            $table->auditActorColumns();

            $table->timestamps();

            $table->softDeletes();

            $table->unique('cooperative_id');

            $table->index('currency');
            $table->index('locale');
            $table->index('timezone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperative_settings');
    }
};
