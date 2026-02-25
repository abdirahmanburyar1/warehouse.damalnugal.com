<?php

namespace App\Http\Controllers;

use App\Models\EmailNotificationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class ReportScheduleController extends Controller
{
    private const SCHEDULE_KEYS = [
        'monthly_received_report' => ['key' => 'monthly_received_report_schedule', 'method' => 'monthlyReceivedReportSchedule', 'default' => ['day_of_month' => 1, 'time' => '01:00']],
        'issue_quantities' => ['key' => 'issue_quantities_schedule', 'method' => 'issueQuantitiesSchedule', 'default' => ['day_of_month' => 1, 'time' => '02:00']],
        'monthly_consumption' => ['key' => 'monthly_consumption_schedule', 'method' => 'monthlyConsumptionSchedule', 'default' => ['day_of_month' => 1, 'time' => '02:00']],
        'inventory_monthly_report' => ['key' => 'inventory_monthly_report_schedule', 'method' => 'inventoryMonthlyReportSchedule', 'default' => ['day_of_month' => 1, 'time' => '06:00']],
        'orders_quarterly' => ['key' => 'orders_quarterly_schedule', 'method' => 'ordersQuarterlySchedule', 'default' => ['time' => '01:00'], 'quarterly' => true],
        'warehouse_amc' => ['key' => 'warehouse_amc_schedule', 'method' => 'warehouseAmcSchedule', 'default' => ['day_of_month' => 1, 'time' => '03:00']],
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
            }
            EmailNotificationSetting::updateOrCreate(
                ['key' => $def['key']],
                ['enabled' => $enabled, 'config' => $config]
            );
        }

        return back()->with('success', 'Report schedule settings saved. Ensure cron runs: * * * * * php artisan schedule:run');
    }
}
