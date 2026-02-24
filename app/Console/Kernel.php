<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('assets:notify-warranty-expiring')->daily();
        $schedule->command('assets:generate-maintenance-schedules')->dailyAt('01:10');
        $schedule->command('assets:notify-maintenance-due')->dailyAt('08:00');
        // Generate inventory report on the first day of each month at 00:01 AM
        $schedule->command('inventory:generate-report')->monthlyOn(1, '00:01');
        // Generate AMC records on the first day of each month at 00:01 AM
        $schedule->command('warehouse:generate-amc')->monthlyOn(1, '00:01');
        // Send low stock notification emails twice a day (9 AM and 3 PM)
        $schedule->command('inventory:notify-low-stock')->twiceDaily(9, 15);
        $schedule->command('inventory:check-low-stock')->everyFiveMinutes();
        // Expiry items: run every minute so the programmable send time (e.g. 13:10) can be hit; command exits immediately if time doesn't match
        $schedule->command('inventory:notify-expiry-items')->everyMinute();
        
        // Schedule quarterly order generation on the last day of each quarter (Mar 31, Jun 30, Sep 30, Dec 31)
        $schedule->command('orders:generate-quarterly')
            ->quarterly()
            ->lastDayOfQuarter()
            ->at('23:00');
            
        // Generate monthly received quantities report on the 1st day of each month at 01:00 AM
        // This runs after the AMC generation to ensure all data is available
        // It will automatically generate the report for the previous month
        $schedule->command('report:monthly-received-quantities')
            ->monthlyOn(1, '01:00')
            ->appendOutputTo(storage_path('logs/monthly-reports.log'))
            ->emailOutputOnFailure(config('mail.admin_address'));
            
        // Generate monthly issued quantities report on the 1st day of each month at 01:30 AM
        // This runs after the received quantities report to ensure all data is processed sequentially
        // It will automatically generate the report for the previous month
        $schedule->command('report:issue-quantities')
            ->monthlyOn(1, '01:30')
            ->appendOutputTo(storage_path('logs/monthly-reports.log'))
            ->emailOutputOnFailure(config('mail.admin_address'));
            
        // Generate monthly inventory reports on the 28th day of each month at 23:55 PM
        // This ensures the report captures almost all the month's data while still being available before month-end
        $schedule->command('report:generate-inventory')
            ->monthlyOn(28, '23:55')
            ->appendOutputTo(storage_path('logs/monthly-inventory-report.log'));
            
        // Clean up empty inventory items and inventories weekly on Sunday at 2:00 AM
        $schedule->command('inventory:cleanup-empty')
            ->weekly()
            ->sundays()
            ->at('02:00')
            ->appendOutputTo(storage_path('logs/inventory-cleanup.log'))
            ->emailOutputOnFailure(config('mail.admin_address'));
            
        // Calculate asset depreciation monthly on the 1st day at 02:00 AM
        // This ensures depreciation is calculated at the beginning of each month
        $schedule->command('assets:schedule-depreciation --frequency=monthly --queue')
            ->monthlyOn(1, '02:00')
            ->appendOutputTo(storage_path('logs/asset-depreciation.log'))
            ->emailOutputOnFailure(config('mail.admin_address'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
