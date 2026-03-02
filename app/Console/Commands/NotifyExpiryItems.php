<?php

namespace App\Console\Commands;

use App\Mail\ExpiryItemsNotification;
use App\Models\EmailNotificationSetting;
use App\Models\InventoryItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyExpiryItems extends Command
{
    protected $signature = 'inventory:notify-expiry-items {--force : Send now regardless of configured send time (for testing)}';
    protected $description = 'Send email notifications for expiry items by policy: already expired, expiring in 6 months, expiring in 1 year (role-based)';

    public function handle(): int
    {
        $setting = EmailNotificationSetting::expiryItems();
        if (!$setting || !$setting->enabled) {
            $this->info('Expiry items notification is disabled or not configured.');
            return self::SUCCESS;
        }

        $config = $setting->config ?? [];
        $roleIds = $config['role_ids'] ?? [];
        $notifyExpired = (bool) ($config['notify_expired'] ?? true);
        $notify6Months = (bool) ($config['notify_expiring_6_months'] ?? true);
        $notify1Year = (bool) ($config['notify_expiring_1_year'] ?? true);

        if (empty($roleIds)) {
            $this->warn('No roles configured for expiry notifications.');
            return self::SUCCESS;
        }

        if (!$notifyExpired && !$notify6Months && !$notify1Year) {
            $this->info('No expiry timeframes enabled in policy.');
            return self::SUCCESS;
        }

        $now = Carbon::now();

        // Programmable send time: only run at configured time (e.g. 07:00 or 13:40), unless --force
        $sendTimeRaw = $config['send_time'] ?? '07:00';
        $sendTime = $this->normalizeSendTime($sendTimeRaw);
        $currentTime = $now->format('H:i');
        $appTz = config('app.timezone', 'UTC');

        if (!$this->option('force')) {
            if ($currentTime !== $sendTime) {
                $this->info('Not the configured send time (' . $sendTime . '). Current app time: ' . $currentTime . ' (' . $appTz . '). Skipping. Use --force to send now.');
                return self::SUCCESS;
            }
        } else {
            $this->info('Force flag set: sending regardless of send time.');
        }

        Log::channel('single')->info('Expiry notification running: sending at configured time.', [
            'send_time' => $sendTime,
            'app_timezone' => $appTz,
        ]);

        $in6Months = $now->copy()->addMonths(6);
        $in1Year = $now->copy()->addYear();

        $interval6 = (int) ($config['expiring_6_months_interval_days'] ?? 4);
        $interval1Year = (int) ($config['expiring_1_year_interval_days'] ?? 14);
        if ($interval6 < 1) {
            $interval6 = 4;
        }
        if ($interval1Year < 1) {
            $interval1Year = 14;
        }

        $dayOfYear = (int) $now->format('z');
        $include6MonthsToday = $notify6Months && ($interval6 > 0) && ($dayOfYear % $interval6 === 0);
        $include1YearToday = $notify1Year && ($interval1Year > 0) && ($dayOfYear % $interval1Year === 0);

        $baseQuery = InventoryItem::query()
            ->with(['product', 'warehouse'])
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date');

        $expiredItems = collect();
        $expiring6MonthsItems = collect();
        $expiring1YearItems = collect();

        // Expired: daily at 7am
        if ($notifyExpired) {
            $expiredItems = (clone $baseQuery)
                ->where('expiry_date', '<', $now)
                ->orderBy('expiry_date')
                ->get();
        }

        // 6 months: twice a week (every 4 days)
        if ($include6MonthsToday) {
            $expiring6MonthsItems = (clone $baseQuery)
                ->where('expiry_date', '>=', $now)
                ->where('expiry_date', '<=', $in6Months)
                ->orderBy('expiry_date')
                ->get();
        }

        // 1 year: every two weeks (every 14 days)
        if ($include1YearToday) {
            $expiring1YearItems = (clone $baseQuery)
                ->where('expiry_date', '>', $in6Months)
                ->where('expiry_date', '<=', $in1Year)
                ->orderBy('expiry_date')
                ->get();
        }

        $total = $expiredItems->count() + $expiring6MonthsItems->count() + $expiring1YearItems->count();
        if ($total === 0) {
            Log::channel('single')->info('Expiry notification: no items in configured ranges, skipping send.');
            $this->info('No items in the configured expiry ranges.');
            return self::SUCCESS;
        }

        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))
            ->get()
            ->unique('id');

        if ($users->isEmpty()) {
            $this->warn('No active users found with the configured roles.');
            return self::SUCCESS;
        }

        foreach ($users as $user) {
            if (empty($user->email)) {
                $this->warn("Skipping user ID {$user->id}: no email address.");
                continue;
            }
            try {
                Mail::to($user->email)->send(new ExpiryItemsNotification(
                    $expiredItems,
                    $expiring6MonthsItems,
                    $expiring1YearItems
                ));
                Log::channel('single')->info('Expiry notification sent.', ['to' => $user->email]);
                $this->info("Sent expiry notification to {$user->email}");
            } catch (\Throwable $e) {
                Log::channel('single')->error('Expiry notification send failed.', ['to' => $user->email, 'error' => $e->getMessage()]);
                $this->error("Failed to send to {$user->email}: " . $e->getMessage());
            }
        }

        Log::channel('single')->info('Expiry notifications completed.', ['recipients' => $users->count()]);
        $this->info('Expiry notifications completed.');
        return self::SUCCESS;
    }

    /**
     * Normalize send_time to HH:mm (2-digit hour) for reliable comparison with Carbon::now()->format('H:i').
     */
    private function normalizeSendTime(string $sendTime): string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($sendTime), $m)) {
            return '07:00';
        }
        $hour = (int) $m[1];
        $min = (int) $m[2];
        if ($hour < 0 || $hour > 23 || $min < 0 || $min > 59) {
            return '07:00';
        }
        return sprintf('%02d:%02d', $hour, $min);
    }
}
