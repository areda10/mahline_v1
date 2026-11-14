<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Identity\Authentication\Services\DeviceTrustService;
use Illuminate\Console\Command;

final class PromotePendingTrustedDevices extends Command
{
    protected $signature = 'identity:devices:promote-pending';

    protected $description = 'Promote eligible pending MAHLINE devices to trusted status.';

    public function handle(DeviceTrustService $devices): int
    {
        $count = $devices->promoteExpiredPending();

        $this->info("Promoted {$count} pending device(s).");

        return self::SUCCESS;
    }
}
