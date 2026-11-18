<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Audit\Repositories\AuditEventRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class VerifyMediaAuditIntegrity extends Command
{
    protected $signature = 'media:verify-audit';

    protected $description = 'Vérifie quotidiennement l’intégrité de la chaîne cryptographique d’audit.';

    public function handle(AuditEventRepository $events): int
    {
        if (! $events->verifyIntegrity()) {
            Log::critical('MEDIA audit integrity verification failed.');
            $this->error('La chaîne d’audit est invalide.');

            return self::FAILURE;
        }

        Log::info('MEDIA audit integrity verification succeeded.');
        $this->info('La chaîne d’audit est intègre.');

        return self::SUCCESS;
    }
}
