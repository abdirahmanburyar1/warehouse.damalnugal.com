<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Task schedule (Laravel 11 reads from here; Kernel.php schedule is ignored)
|--------------------------------------------------------------------------
*/
Schedule::command('assets:notify-warranty-expiring')->daily();
Schedule::command('assets:generate-maintenance-schedules')->dailyAt('01:10');
Schedule::command('assets:notify-maintenance-due')->dailyAt('08:00');
Schedule::command('inventory:generate-report')->monthlyOn(1, '00:01');
Schedule::command('warehouse:generate-amc')->monthlyOn(1, '00:01');
Schedule::command('inventory:notify-low-stock')->twiceDaily(9, 15);
Schedule::command('inventory:check-low-stock')->everyFiveMinutes();
// Expiry items: run every minute; command exits early if not the configured send time
Schedule::command('inventory:notify-expiry-items')->everyMinute();

Schedule::command('orders:generate-quarterly')
    ->dailyAt('23:00')
    ->when(function () {
        $d = (int) now()->format('d');
        $m = (int) now()->format('m');
        return ($m === 3 && $d === 31) || ($m === 6 && $d === 30) || ($m === 9 && $d === 30) || ($m === 12 && $d === 31);
    });

Schedule::command('report:monthly-received-quantities')
    ->monthlyOn(1, '01:00')
    ->appendOutputTo(storage_path('logs/monthly-reports.log'))
    ->emailOutputOnFailure(config('mail.admin_address'));

Schedule::command('report:issue-quantities')
    ->monthlyOn(1, '01:30')
    ->appendOutputTo(storage_path('logs/monthly-reports.log'))
    ->emailOutputOnFailure(config('mail.admin_address'));

Schedule::command('report:generate-inventory')
    ->monthlyOn(28, '23:55')
    ->appendOutputTo(storage_path('logs/monthly-inventory-report.log'));

Schedule::command('inventory:cleanup-empty')
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->appendOutputTo(storage_path('logs/inventory-cleanup.log'))
    ->emailOutputOnFailure(config('mail.admin_address'));

Schedule::command('assets:schedule-depreciation --frequency=monthly --queue')
    ->monthlyOn(1, '02:00')
    ->appendOutputTo(storage_path('logs/asset-depreciation.log'))
    ->emailOutputOnFailure(config('mail.admin_address'));
