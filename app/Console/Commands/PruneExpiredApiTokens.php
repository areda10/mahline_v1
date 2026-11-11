<?php

declare(strict_types=1);

namespace App\\Console\\Commands;

use App\\Domains\\Identity\\Authentication\\Services\\ApiTokenService;
use Illuminate\\Console\\Command;

final class PruneExpiredApiTokens extends Command
{
    protected $signature = 'identity:tokens:prune-expired';

    protected $description = 'Permanently remove API tokens retained for 90 days after expiration.';

    public function handle(ApiTokenService $tokens): int
    {
        $deleted = $tokens->pruneExpiredTokens();

        $this->components->info(
            sprintf('%d expired API token(s) permanently removed.', $deleted),
        );

        return self::SUCCESS;
    }
}
