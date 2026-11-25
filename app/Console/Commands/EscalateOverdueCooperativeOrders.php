<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Orders\Services\CooperativeOrderEscalationService;
use Illuminate\Console\Command;

final class EscalateOverdueCooperativeOrders extends Command
{
    protected $signature = 'orders:escalate-overdue-cooperative-orders';

    protected $description = 'Notify assigned super-admins at H+12 and escalate unanswered cooperative orders to root at H+24.';

    public function handle(CooperativeOrderEscalationService $escalations): int
    {
        $count = $escalations->escalateOverdue();

        $this->info("Processed {$count} cooperative order escalation(s).");

        return self::SUCCESS;
    }
}
