<?php

namespace App\Providers;

use App\Events\TransferStatusChanged;
use App\Listeners\TransferStatusChangedListener;
use App\Listeners\StoreUserLoginTime;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Queue\Events\JobFailed;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Login::class => [
            StoreUserLoginTime::class,
        ],
        TransferStatusChanged::class => [
            TransferStatusChangedListener::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [
        \App\Listeners\LogUserAuthentication::class,
    ];

    public function boot(): void
    {
        \App\Models\User::observe(\App\Observers\UserObserver::class);
        Event::listen(JobFailed::class, [\App\Listeners\LogFailedQueueJob::class, 'handle']);
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}