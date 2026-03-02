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
$scheduleFailureEmail = config('mail.admin_address');
$emailOnFailure = function ($event) use ($scheduleFailureEmail) {
    return !empty(trim((string) $scheduleFailureEmail))
        ? $event->emailOutputOnFailure($scheduleFailureEmail)
        : $event;
};

$emailOnFailure(Schedule::command('assets:notify-warranty-expiring')->daily());
$emailOnFailure(Schedule::command('assets:generate-maintenance-schedules')->dailyAt('01:10'));
$emailOnFailure(Schedule::command('assets:notify-maintenance-due')->dailyAt('08:00'));
$emailOnFailure(Schedule::command('inventory:generate-report')->monthlyOn(1, '00:01'));
$emailOnFailure(Schedule::command('inventory:notify-low-stock')->twiceDaily(9, 15));
$emailOnFailure(Schedule::command('inventory:check-low-stock')->everyFiveMinutes());
// Expiry items: run every minute; command exits early if not the configured send time
$emailOnFailure(Schedule::command('inventory:notify-expiry-items')->everyMinute());

// Report/job schedules: run every minute; each command exits unless Settings > Report Schedules matches (day/time)
$emailOnFailure(Schedule::command('report:monthly-received-quantities')
    ->everyMinute()
    ->appendOutputTo(storage_path('logs/monthly-reports.log')));

$emailOnFailure(Schedule::command('report:issue-quantities')
    ->everyMinute()
    ->appendOutputTo(storage_path('logs/monthly-reports.log')));

$emailOnFailure(Schedule::command('consumption:generate')
    ->everyMinute()
    ->appendOutputTo(storage_path('logs/monthly-consumption.log')));

$emailOnFailure(Schedule::command('inventory:generate-monthly-report')
    ->everyMinute()
    ->appendOutputTo(storage_path('logs/monthly-inventory-report.log')));

$emailOnFailure(Schedule::command('orders:generate-quarterly')
    ->everyMinute()
    ->appendOutputTo(storage_path('logs/quarterly-orders.log')));

$emailOnFailure(Schedule::command('warehouse:generate-amc')
    ->everyMinute()
    ->appendOutputTo(storage_path('logs/warehouse-amc.log')));

$emailOnFailure(Schedule::command('report:generate-inventory')
    ->monthlyOn(28, '23:55')
    ->appendOutputTo(storage_path('logs/monthly-inventory-report.log')));

$emailOnFailure(Schedule::command('inventory:cleanup-empty')
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->appendOutputTo(storage_path('logs/inventory-cleanup.log')));

$emailOnFailure(Schedule::command('assets:schedule-depreciation --frequency=monthly --queue')
    ->monthlyOn(1, '02:00')
    ->appendOutputTo(storage_path('logs/asset-depreciation.log')));
