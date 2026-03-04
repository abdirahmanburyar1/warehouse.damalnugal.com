<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailNotificationSetting extends Model
{
    protected $fillable = ['key', 'enabled', 'config'];

    protected $casts = [
        'enabled' => 'boolean',
        'config' => 'array',
    ];

    public static function getForKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }

    public static function getConfig(string $key, array $default = []): array
    {
        $setting = static::getForKey($key);
        if (!$setting || !$setting->enabled) {
            return $default;
        }
        return array_merge($default, $setting->config ?? []);
    }

    public static function expiryItems(): ?self
    {
        return static::getForKey('expiry_items');
    }

    /** Schedule for monthly received quantities report (day_of_month, time in config). */
    public static function monthlyReceivedReportSchedule(): ?self
    {
        return static::getForKey('monthly_received_report_schedule');
    }

    /** Schedule for issue quantities report (day_of_month, time in config). */
    public static function issueQuantitiesSchedule(): ?self
    {
        return static::getForKey('issue_quantities_schedule');
    }

    /** Schedule for monthly consumption data (day_of_month, time in config). */
    public static function monthlyConsumptionSchedule(): ?self
    {
        return static::getForKey('monthly_consumption_schedule');
    }

    /** Schedule for inventory monthly report (day_of_month, time in config). */
    public static function inventoryMonthlyReportSchedule(): ?self
    {
        return static::getForKey('inventory_monthly_report_schedule');
    }

    /** Schedule for quarterly orders (time in config; runs on quarter start days only). */
    public static function ordersQuarterlySchedule(): ?self
    {
        return static::getForKey('orders_quarterly_schedule');
    }

    /** Schedule for warehouse AMC (day_of_month, time in config). */
    public static function warehouseAmcSchedule(): ?self
    {
        return static::getForKey('warehouse_amc_schedule');
    }

    /** Schedule for facility monthly (LMIS) report (day_of_month, time in config). */
    public static function facilityMonthlyReportSchedule(): ?self
    {
        return static::getForKey('facility_monthly_report_schedule');
    }

    /**
     * Report Submission Rate config: expected_reports (per period), ontime_day (1-31).
     * expected_reports=1 means 1 per period (12/year for monthly). ontime_day=5 means 1-5 ontime, 6+ late.
     */
    public static function reportSubmissionRateConfig(): ?self
    {
        return static::getForKey('report_submission_rate_config');
    }

    public static function getReportSubmissionRateConfig(): array
    {
        $defaults = [
            'expected_reports' => 1,
            'ontime_day' => 5,
        ];
        $setting = static::reportSubmissionRateConfig();
        if (! $setting || ! is_array($setting->config)) {
            return $defaults;
        }
        return array_merge($defaults, $setting->config);
    }
}
