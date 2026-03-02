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

    /** Schedule for inventory monthly report (day_of_month, time, expected_number_of_reports, ontime_day_start, ontime_day_end in config). */
    public static function inventoryMonthlyReportSchedule(): ?self
    {
        return static::getForKey('inventory_monthly_report_schedule');
    }

    /** Get inventory report submission expectation config: expected_number_of_reports (default 1), ontime_day_start (1), ontime_day_end (3). */
    public static function inventoryReportExpectationConfig(): array
    {
        $setting = static::inventoryMonthlyReportSchedule();
        $config = $setting?->config ?? [];
        return [
            'expected_number_of_reports' => (int) ($config['expected_number_of_reports'] ?? 1),
            'ontime_day_start' => (int) ($config['ontime_day_start'] ?? 1),
            'ontime_day_end' => (int) ($config['ontime_day_end'] ?? 3),
        ];
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
}
