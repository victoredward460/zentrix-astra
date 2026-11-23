<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('file-checks:prune')->daily();
Schedule::command('safe-navigation:prune')->daily();
Schedule::command('agent:prune')->daily();
Schedule::command('platform-audit:prune')->daily();
