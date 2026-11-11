<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');\n\nSchedule::command('identity:tokens:prune-expired')\n    ->daily()\n    ->withoutOverlapping();
