<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('nas:purge-trash')
    ->dailyAt('01:00')
    ->withoutOverlapping();

Schedule::command('nas:recalculate-quotas')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('nas:remind-access-requests')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('nas:cleanup-chunks')
    ->dailyAt('03:00')
    ->withoutOverlapping();
