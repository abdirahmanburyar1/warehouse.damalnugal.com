<?php

namespace App\Console\Commands;

use App\Models\EmailNotificationSetting;
use App\Jobs\GenerateMonthlyInventoryReportJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateInventoryMonthlyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:generate-monthly-report {--month= : The month in YYYY-MM format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly inventory reports using InventoryReport and InventoryReportItem models';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $monthArg = $this->option('month');
        $today = Carbon::now();

        // When run from scheduler (no --month): only run if schedule is enabled AND today matches configured day + time
        if (!$monthArg) {
            $setting = EmailNotificationSetting::inventoryMonthlyReportSchedule();
            if (!$setting || !$setting->enabled) {
                return 0;
            }
            $config = $setting->config ?? [];
            $dayOfMonth = (int) ($config['day_of_month'] ?? 1);
            $time = $this->normalizeTime($config['time'] ?? '06:00');
            $currentTime = $today->format('H:i');
            if ($today->day != $dayOfMonth || $currentTime !== $time) {
                return 0;
            }
        }

        // Default to previous month since inventory reports are generated on the first day for the previous month
        $monthYear = $monthArg ?? Carbon::now()->subMonth()->format('Y-m');

        $this->info("Generating monthly inventory report for {$monthYear}");
        
        try {
            // Execute the job directly (synchronously) to avoid queue serialization issues
            $job = new GenerateMonthlyInventoryReportJob($monthYear);
            $job->handle();
            
            $this->info("Monthly inventory report generated successfully for {$monthYear}");
            $this->info("An email notification has been sent to abdirahman.buryar@gmail.com");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Error generating report: {$e->getMessage()}");
            return 1;
        }
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $parts = explode(':', $time);
            return sprintf('%02d:%02d', (int) $parts[0], (int) ($parts[1] ?? 0));
        }
        return '06:00';
    }
}
