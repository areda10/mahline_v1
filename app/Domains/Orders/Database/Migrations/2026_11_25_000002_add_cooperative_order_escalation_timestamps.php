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
            $table->timestamp('super_admin_notified_at')->nullable()->after('response_overdue_at');
            $table->timestamp('root_escalated_at')->nullable()->after('super_admin_notified_at');
            $table->index(['status', 'super_admin_notified_at'], 'coop_orders_super_admin_escalation_idx');
            $table->index(['status', 'root_escalated_at'], 'coop_orders_root_escalation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cooperative_orders', function (Blueprint $table): void {
            $table->dropIndex('coop_orders_super_admin_escalation_idx');
            $table->dropIndex('coop_orders_root_escalation_idx');
            $table->dropColumn(['super_admin_notified_at', 'root_escalated_at']);
        });
    }
};
