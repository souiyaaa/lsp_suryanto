<?php

use App\Services\GarageStatusService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('garages:refresh-status', function (GarageStatusService $garageStatusService) {
    $garageStatusService->refreshAll();
    $this->info('Garage status refreshed for ' . today()->toDateString());
})->purpose('Recompute garages.is_used for today (Asia/Jakarta)');

Schedule::command('garages:refresh-status')->dailyAt('00:00')->timezone('Asia/Jakarta');
