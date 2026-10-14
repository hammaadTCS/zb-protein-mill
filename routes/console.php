<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// BR-OPS-08 (T7.8): the integrity check every night at 01:30 Pakistan time.
Schedule::command('zb:verify')->dailyAt('20:30')->timezone('UTC')->withoutOverlapping();
