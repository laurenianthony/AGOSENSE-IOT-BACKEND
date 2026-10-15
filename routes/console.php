<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sensors:aggregate --scope=hourly')->hourly()->withoutOverlapping();
Schedule::command('sensors:aggregate --scope=daily')->dailyAt('00:15')->withoutOverlapping();
