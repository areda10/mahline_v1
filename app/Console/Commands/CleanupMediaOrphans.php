<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Media\Services\MediaOrphanCleanupService;
use Illuminate\Console\Command;

final class CleanupMediaOrphans extends Command
{
    protected $signature = 'media:cleanup-orphans';

    protected $description = 'Détecte et supprime les fichiers MEDIA orphelins après le délai de sécurité.';

    public function handle(MediaOrphanCleanupService $cleanup): int
    {
        $count = $cleanup->detectAndPurge();

        $this->info($count.' fichier(s) orphelin(s) supprimé(s).');

        return self::SUCCESS;
    }
}
