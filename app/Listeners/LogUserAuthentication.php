<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use App\Models\SystemAudit;
use App\Models\User;

class LogUserAuthentication
{
    /**
     * Handle user login events.
     */
    public function handleUserLogin(Login $event): void
    {
        $this->logAuthEvent($event->user, 'login');
    }

    /**
     * Handle user logout events.
     */
    public function handleUserLogout(Logout $event): void
    {
        if ($event->user) {
            $this->logAuthEvent($event->user, 'logout');
        }
    }

    /**
     * Log the authentication event.
     */
    protected function logAuthEvent($user, string $action): void
    {
        $metadata = [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if ($user && !empty($user->facility_id)) {
            $metadata['facility_id'] = $user->facility_id;
        }

        SystemAudit::create([
            'user_id' => $user->id,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'action' => $action,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            Login::class,
            [LogUserAuthentication::class, 'handleUserLogin']
        );

        $events->listen(
            Logout::class,
            [LogUserAuthentication::class, 'handleUserLogout']
        );
    }
}
