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
}
