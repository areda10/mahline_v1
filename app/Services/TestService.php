<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Foundation\Services\BaseService;

class TestService extends BaseService
{
    public function hello(): string
    {
        return 'MAHLINE Framework';
    }
}
