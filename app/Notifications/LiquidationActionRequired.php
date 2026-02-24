<?php

namespace App\Notifications;

use App\Models\Liquidate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LiquidationActionRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = 10;

    public const ACTION_NEEDS_REVIEW = 'needs_review';
    public const ACTION_READY_FOR_APPROVAL = 'ready_for_approval';

    protected Liquidate $liquidate;
    protected string $action;

    /**
     * @param  Liquidate  $liquidate
     * @param  string  $action  self::ACTION_NEEDS_REVIEW or self::ACTION_READY_FOR_APPROVAL
     */
    public function __construct(Liquidate $liquidate, string $action)
    {
        $this->liquidate = $liquidate->load(['liquidatedBy', 'items']);
        $this->action = $action;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $liquidateId = $this->liquidate->liquidate_id ?? '#' . $this->liquidate->id;
        $source = $this->liquidate->source ? str_replace('_', ' ', $this->liquidate->source) : 'N/A';
        $path = route('liquidate-disposal.liquidates.show', [$this->liquidate->id], false);
        $showUrl = str_starts_with($path, 'http') ? $path : rtrim(config('app.url'), '/') . (str_starts_with($path, '/') ? $path : '/' . $path);

        if ($this->action === self::ACTION_NEEDS_REVIEW) {
            $subject = 'Liquidation ' . $liquidateId . ' needs your review';
            $line1 = 'A liquidation has been submitted and needs your review.';
        } else {
            $subject = 'Liquidation ' . $liquidateId . ' is ready for approval';
            $line1 = 'Liquidation ' . $liquidateId . ' has been reviewed and is ready for your approval or rejection.';
        }

        return (new MailMessage)
            ->subject($subject . ' - ' . config('app.name'))
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line($line1)
            ->line('Liquidation: ' . $liquidateId)
            ->line('Source: ' . $source)
            ->action('View liquidation', $showUrl)
            ->line('Please log in and take the required action.');
    }

    public function toArray($notifiable)
    {
        return [
            'liquidate_id' => $this->liquidate->id,
            'liquidate_number' => $this->liquidate->liquidate_id,
            'action' => $this->action,
        ];
    }
}
