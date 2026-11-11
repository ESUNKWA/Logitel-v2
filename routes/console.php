<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Affectations left in the « Corbeille » longer than the retention period are erased for good.
Schedule::command('hotel:purge-trash')->dailyAt('03:30');
