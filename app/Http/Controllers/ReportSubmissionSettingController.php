<?php

namespace App\Http\Controllers;

use App\Models\EmailNotificationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class ReportSubmissionSettingController extends Controller
{
    private const DEFAULTS = [
        'expected_reports' => 1,
        'ontime_day' => 5,
    ];

    public function index()
    {
        if (! Schema::hasTable('email_notification_settings')) {
            return Inertia::render('Settings/ReportSubmission/Index', [
                'config' => self::DEFAULTS,
            ]);
        }

        $setting = EmailNotificationSetting::reportSubmissionRateConfig();
        $config = $setting && is_array($setting->config)
            ? array_merge(self::DEFAULTS, $setting->config)
            : self::DEFAULTS;

        return Inertia::render('Settings/ReportSubmission/Index', [
            'config' => $config,
        ]);
    }

    public function update(Request $request)
    {
        if (! Schema::hasTable('email_notification_settings')) {
            return back()->with('error', 'Settings table is not available.');
        }

        $request->validate([
            'expected_reports' => 'required|integer|min:1|max:24',
            'ontime_day' => 'required|integer|min:1|max:31',
        ]);

        $config = [
            'expected_reports' => (int) $request->input('expected_reports'),
            'ontime_day' => (int) $request->input('ontime_day'),
        ];

        EmailNotificationSetting::updateOrCreate(
            ['key' => 'report_submission_rate_config'],
            ['enabled' => true, 'config' => $config]
        );

        return back()->with('success', 'Report Submission Rate settings saved.');
    }
}
