<?php

namespace App\Http\Controllers;

use App\Models\EmailNotificationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class ReportScheduleController extends Controller
{
    public function index()
    {
        if (!Schema::hasTable('email_notification_settings')) {
            return Inertia::render('Settings/ReportSchedules/Index', [
                'monthlyReceivedReport' => $this->defaultMonthlyReceivedConfig(),
            ]);
        }

        $setting = EmailNotificationSetting::monthlyReceivedReportSchedule();
        $config = $setting ? ($setting->config ?? []) : [];
        $monthlyReceivedReport = [
            'enabled' => $setting ? $setting->enabled : false,
            'day_of_month' => (int) ($config['day_of_month'] ?? 1),
            'time' => $config['time'] ?? '01:00',
        ];

        return Inertia::render('Settings/ReportSchedules/Index', [
            'monthlyReceivedReport' => $monthlyReceivedReport,
        ]);
    }

    public function update(Request $request)
    {
        if (!Schema::hasTable('email_notification_settings')) {
            return back()->with('error', 'Settings table is not available. Please run migrations.');
        }

        $validated = $request->validate([
            'monthly_received_report.enabled' => 'boolean',
            'monthly_received_report.day_of_month' => 'required|integer|min:1|max:28',
            'monthly_received_report.time' => 'required|string|regex:/^\d{1,2}:\d{2}$/',
        ]);

        $data = $validated['monthly_received_report'] ?? [];
        $enabled = (bool) ($data['enabled'] ?? false);
        $dayOfMonth = (int) ($data['day_of_month'] ?? 1);
        $time = $data['time'] ?? '01:00';
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $parts = explode(':', $time);
            $time = sprintf('%02d:%02d', (int) $parts[0], (int) ($parts[1] ?? 0));
        } else {
            $time = '01:00';
        }

        EmailNotificationSetting::updateOrCreate(
            ['key' => 'monthly_received_report_schedule'],
            [
                'enabled' => $enabled,
                'config' => [
                    'day_of_month' => $dayOfMonth,
                    'time' => $time,
                ],
            ]
        );

        return back()->with('success', 'Report schedule settings saved. Use Laravel scheduler (e.g. cron: * * * * * php artisan schedule:run) so the command runs at the configured day and time.');
    }

    private function defaultMonthlyReceivedConfig(): array
    {
        return [
            'enabled' => false,
            'day_of_month' => 1,
            'time' => '01:00',
        ];
    }
}
