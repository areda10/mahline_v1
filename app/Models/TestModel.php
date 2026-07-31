<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Foundation\Models\BaseModel;

class TestModel extends BaseModel
{
    protected $table = 'test_models';

    protected $fillable = [
        'name',
    ];
}