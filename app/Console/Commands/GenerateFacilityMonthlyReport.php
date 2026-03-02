<?php

namespace App\Console\Commands;

use App\Http\Controllers\ReportController;
use App\Models\EmailNotificationSetting;
use App\Models\Facility;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateFacilityMonthlyReport extends Command
{
    protected $signature = 'facility:generate-monthly-report {--month= : The month in YYYY-MM format}';

    protected $description = 'Generate facility monthly (LMIS) reports for all facilities for the given month (draft only; skips non-draft).';

    public function handle(): int
    {
        $monthArg = $this->option('month');
        $today = Carbon::now();

        if (!$monthArg) {
            $setting = EmailNotificationSetting::facilityMonthlyReportSchedule();
            if (!$setting || !$setting->enabled) {
                Log::info('Facility LMIS schedule: skipped (disabled or not configured).');
                $this->info('Facility monthly report schedule is disabled or not configured.');
                return 0;
            }
            $config = $setting->config ?? [];
            $dayOfMonth = (int) ($config['day_of_month'] ?? 1);
            $time = $this->normalizeTime($config['time'] ?? '04:00');
            $currentTime = $today->format('H:i');
            if ($today->day != $dayOfMonth || $currentTime !== $time) {
                Log::debug('Facility LMIS schedule: not run window', [
                    'today_day' => $today->day,
                    'today_time' => $currentTime,
                    'scheduled_day' => $dayOfMonth,
                    'scheduled_time' => $time,
                ]);
                return 0;
            }
        }

        $monthYear = $monthArg ?? Carbon::now()->subMonth()->format('Y-m');
        [$year, $month] = explode('-', $monthYear);
        $year = (int) $year;
        $month = (int) $month;

        $controller = app(ReportController::class);
        $facilities = Facility::query()->pluck('id');
        $totalFacilities = $facilities->count();

        Log::info('Facility LMIS schedule: started', [
            'month' => $monthYear,
            'facility_count' => $totalFacilities,
        ]);
        $this->info("Generating facility monthly reports for {$monthYear} ({$totalFacilities} facilities).");

        $generated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($facilities as $facilityId) {
            try {
                $result = $controller->generateFacilityMonthlyReportForFacility($facilityId, $year, $month);
                if (!empty($result['skipped'])) {
                    $skipped++;
                    Log::debug('Facility LMIS schedule: facility skipped', [
                        'facility_id' => $facilityId,
                        'reason' => $result['reason'] ?? 'unknown',
                    ]);
                    continue;
                }
                $generated++;
                Log::info('Facility LMIS schedule: report generated', [
                    'facility_id' => $facilityId,
                    'report_id' => $result['report_id'] ?? null,
                    'created_count' => $result['created_count'] ?? 0,
                    'updated_count' => $result['updated_count'] ?? 0,
                    'total_products' => $result['total_products'] ?? 0,
                ]);
            } catch (\Throwable $e) {
                $errors++;
                Log::error('Facility LMIS schedule: facility failed', [
                    'facility_id' => $facilityId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("Facility {$facilityId}: {$e->getMessage()}");
            }
        }

        Log::info('Facility LMIS schedule: completed', [
            'month' => $monthYear,
            'generated' => $generated,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
        $this->info("Done. Generated: {$generated}, skipped: {$skipped}, errors: {$errors}.");
        return $errors > 0 ? 1 : 0;
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $parts = explode(':', $time);
            return sprintf('%02d:%02d', (int) $parts[0], (int) ($parts[1] ?? 0));
        }
        return '04:00';
    }
}
