<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Media\Services\MediaCleanupService;
use Illuminate\Console\Command;

final class PurgeExpiredMedia extends Command
{
    protected $signature = 'media:purge-expired';

    protected $description = 'Purge progressivement les médias supprimés au-delà de la rétention configurée.';

    public function handle(MediaCleanupService $cleanup): int
    {
        $count = $cleanup->purgeExpired();

        $this->info($count.' média(s) purgé(s).');

        return self::SUCCESS;
    }
}
