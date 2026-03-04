<?php

namespace App\Http\Controllers;

use App\Models\EmailNotificationSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class ReportScheduleController extends Controller
{
    private const SCHEDULE_KEYS = [
        'monthly_received_report' => ['key' => 'monthly_received_report_schedule', 'method' => 'monthlyReceivedReportSchedule', 'default' => ['day_of_month' => 1, 'time' => '01:00']],
        'issue_quantities' => ['key' => 'issue_quantities_schedule', 'method' => 'issueQuantitiesSchedule', 'default' => ['day_of_month' => 1, 'time' => '02:00']],
        'monthly_consumption' => ['key' => 'monthly_consumption_schedule', 'method' => 'monthlyConsumptionSchedule', 'default' => ['day_of_month' => 1, 'time' => '02:00']],
        'inventory_monthly_report' => ['key' => 'inventory_monthly_report_schedule', 'method' => 'inventoryMonthlyReportSchedule', 'default' => ['day_of_month' => 1, 'time' => '06:00']],
        'orders_quarterly' => ['key' => 'orders_quarterly_schedule', 'method' => 'ordersQuarterlySchedule', 'default' => ['time' => '01:00', 'quarter_start_month' => 12], 'quarterly' => true],
        'warehouse_amc' => ['key' => 'warehouse_amc_schedule', 'method' => 'warehouseAmcSchedule', 'default' => ['day_of_month' => 1, 'time' => '03:00']],
        'facility_monthly_report' => ['key' => 'facility_monthly_report_schedule', 'method' => 'facilityMonthlyReportSchedule', 'default' => ['day_of_month' => 1, 'time' => '04:00']],
    ];

    public function index()
    {
        $schedules = [];
        foreach (self::SCHEDULE_KEYS as $slug => $def) {
            $setting = Schema::hasTable('email_notification_settings')
                ? EmailNotificationSetting::{$def['method']}()
                : null;
            $config = $setting ? ($setting->config ?? []) : [];
            $default = $def['default'];
            $schedules[$slug] = [
                'enabled' => $setting ? $setting->enabled : false,
                'day_of_month' => (int) ($config['day_of_month'] ?? $default['day_of_month'] ?? 1),
                'time' => $config['time'] ?? $default['time'] ?? '01:00',
            ];
            if (!empty($def['quarterly'])) {
                unset($schedules[$slug]['day_of_month']);
                $schedules[$slug]['quarter_start_month'] = (int) ($config['quarter_start_month'] ?? $default['quarter_start_month'] ?? 1);
            }
        }

        return Inertia::render('Settings/ReportSchedules/Index', [
            'schedules' => $schedules,
        ]);
    }

    public function update(Request $request)
    {
        if (!Schema::hasTable('email_notification_settings')) {
            return back()->with('error', 'Settings table is not available. Please run migrations.');
        }

        $rules = [];
        foreach (self::SCHEDULE_KEYS as $slug => $def) {
            $rules["{$slug}.enabled"] = 'boolean';
            $rules["{$slug}.time"] = 'required|string|regex:/^\d{1,2}:\d{2}$/';
            if (empty($def['quarterly'])) {
                $rules["{$slug}.day_of_month"] = 'required|integer|min:1|max:28';
            } else {
                $rules["{$slug}.quarter_start_month"] = 'required|integer|min:1|max:12';
            }
        }
        $validated = $request->validate($rules);

        foreach (self::SCHEDULE_KEYS as $slug => $def) {
            $data = $validated[$slug] ?? [];
            $enabled = (bool) ($data['enabled'] ?? false);
            $time = $data['time'] ?? $def['default']['time'] ?? '01:00';
            if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
                $parts = explode(':', $time);
                $time = sprintf('%02d:%02d', (int) $parts[0], (int) ($parts[1] ?? 0));
            } else {
                $time = $def['default']['time'] ?? '01:00';
            }
            $config = ['time' => $time];
            if (empty($def['quarterly'])) {
                $config['day_of_month'] = (int) ($data['day_of_month'] ?? 1);
            } else {
                $config['quarter_start_month'] = (int) ($data['quarter_start_month'] ?? 1);
            }
            EmailNotificationSetting::updateOrCreate(
                ['key' => $def['key']],
                ['enabled' => $enabled, 'config' => $config]
            );
        }

        return back()->with('success', 'Report schedule settings saved. Ensure cron runs: * * * * * php artisan schedule:run');
    }

    /**
     * Artisan command and default options for each runnable schedule (manual run).
     */
    private const RUN_COMMANDS = [
        'monthly_received_report' => ['command' => 'report:monthly-received-quantities', 'options' => ['--month' => null]],
        'issue_quantities' => ['command' => 'report:issue-quantities', 'options' => ['--month' => null]],
        'monthly_consumption' => ['command' => 'consumption:generate', 'options' => []],
        'inventory_monthly_report' => ['command' => 'inventory:generate-monthly-report', 'options' => ['--month' => null]],
        'orders_quarterly' => ['command' => 'orders:generate-quarterly', 'options' => []],
        'warehouse_amc' => ['command' => 'warehouse:generate-amc', 'options' => ['--month' => null]],
        'facility_monthly_report' => ['command' => 'facility:generate-monthly-report', 'options' => ['--month' => null]],
    ];

    /**
     * Run a scheduled task manually by slug. Uses previous month for monthly reports if month not provided.
     */
    public function runScheduleNow(Request $request)
    {
        $request->validate([
            'slug' => 'required|string|in:' . implode(',', array_keys(self::RUN_COMMANDS)),
            'month' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
        ]);
        $slug = $request->slug;
        $def = self::RUN_COMMANDS[$slug];
        $month = $request->filled('month') ? $request->month : Carbon::now()->subMonth()->format('Y-m');

        $params = [];
        foreach ($def['options'] as $key => $value) {
            if ($value === null && $key === '--month') {
                $params[$key] = $month;
            } elseif ($value !== null) {
                $params[$key] = $value;
            }
        }

        if ($slug === 'orders_quarterly') {
            $setting = EmailNotificationSetting::ordersQuarterlySchedule();
            $config = $setting ? ($setting->config ?? []) : [];
            $startMonth = (int) ($config['quarter_start_month'] ?? 1);
            [$quarter, $year] = self::getCurrentQuarterAndYear(Carbon::now(), $startMonth);
            Artisan::call($def['command'], ['quarter' => $quarter, 'year' => $year]);
        } else {
            Artisan::call($def['command'], $params);
        }

        return back()->with('success', "Schedule \"{$slug}\" run completed. Check logs if no output.");
    }

    /**
     * Get current quarter (1-4) and year for a given date and quarter start month.
     * E.g. startMonth=1 → Jan,Apr,Jul,Oct; startMonth=12 → Dec,Mar,Jun,Sep.
     * When start month is Oct–Dec, being on that month means we start a new fiscal year (year+1).
     */
    private static function getCurrentQuarterAndYear(Carbon $date, int $startMonth): array
    {
        $m = (int) $date->month;
        $offset = ($m - $startMonth + 12) % 12;
        $quarter = (int) floor($offset / 3) + 1;
        $year = (int) $date->year;
        if ($startMonth >= 10 && $m === $startMonth) {
            $year++;
        }
        return [$quarter, $year];
    }

    /**
     * Run the inventory monthly report now for a given month (default: previous month).
     */
    public function runInventoryMonthlyReportNow(Request $request)
    {
        $request->validate([
            'month' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
        ]);
        $month = $request->filled('month')
            ? $request->month
            : Carbon::now()->subMonth()->format('Y-m');

        Artisan::call('inventory:generate-monthly-report', ['--month' => $month]);

        return back()->with('success', "Inventory monthly report run completed for {$month}. Check logs if no output.");
    }
}
