<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PermissionsChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = 10;

    /**
     * @var User
     */
    protected $user;

    /**
     * Display names of permissions that were added.
     *
     * @var array<string>
     */
    protected $added;

    /**
     * Display names of permissions that were removed.
     *
     * @var array<string>
     */
    protected $removed;

    /**
     * Create a new notification instance.
     *
     * @param  User  $user  The user whose permissions changed
     * @param  array<string>  $added  Display names of newly granted permissions
     * @param  array<string>  $removed  Display names of removed permissions
     * @return void
     */
    public function __construct(User $user, array $added = [], array $removed = [])
    {
        $this->user = $user;
        $this->added = $added;
        $this->removed = $removed;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $mailMessage = (new MailMessage)
            ->subject('Your permissions have been updated - ' . config('app.name'))
            ->greeting('Hello ' . $this->user->name . '!')
            ->line('Your access permissions on ' . config('app.name') . ' have been updated.');

        if (count($this->added) > 0) {
            $mailMessage->line('Permissions granted:');
            foreach ($this->added as $name) {
                $mailMessage->line('• ' . $name);
            }
            $mailMessage->line('');
        }

        if (count($this->removed) > 0) {
            $mailMessage->line('Permissions removed:');
            foreach ($this->removed as $name) {
                $mailMessage->line('• ' . $name);
            }
            $mailMessage->line('');
        }

        $mailMessage
            ->action('Log in to your account', url('/login'))
            ->line('If you have any questions, please contact your administrator.');

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'user_id' => $this->user->id,
            'added' => $this->added,
            'removed' => $this->removed,
        ];
    }
}
