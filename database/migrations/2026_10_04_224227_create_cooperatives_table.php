<?php

declare(strict_types=1);

use App\Domains\Cooperatives\Enums\CooperativeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperatives', function (Blueprint $table): void {
            $table->ulidPrimary();

            $table->string('name');

            $table->string('rib');

            $table->string('tax_id')->nullable();

            $table->ulid('address_id');

            $table->string('phone');

            $table->string('email');

            $table->string('rc')->nullable();

            $table->string('ice');

            $table->string('status')
                ->default(CooperativeStatus::PENDING->value);

            $table->auditActorColumns();

            $table->timestamps();

            $table->softDeletes();

            $table->index('address_id');
            $table->index('status');
            $table->index('email');
            $table->index('ice');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperatives');
    }
};
