<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderActionRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = 10;

    public const ACTION_NEEDS_REVIEW = 'needs_review';
    public const ACTION_READY_FOR_APPROVAL = 'ready_for_approval';
    public const ACTION_READY_FOR_PROCESSING = 'ready_for_processing';
    public const ACTION_READY_FOR_DISPATCH = 'ready_for_dispatch';

    protected Order $order;
    protected string $action;

    /**
     * @param  Order  $order
     * @param  string  $action  One of ACTION_* constants (next step in workflow)
     */
    public function __construct(Order $order, string $action)
    {
        $this->order = $order->load('facility');
        $this->action = $action;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $orderNumber = $this->order->order_number ?? '#' . $this->order->id;
        $path = route('orders.show', [$this->order->id], false);
        $showUrl = str_starts_with($path, 'http') ? $path : rtrim(config('app.url'), '/') . (str_starts_with($path, '/') ? $path : '/' . $path);

        $facilityName = $this->order->facility?->name ?? 'N/A';

        $subject = '';
        $line1 = '';
        switch ($this->action) {
            case self::ACTION_NEEDS_REVIEW:
                $subject = 'Order ' . $orderNumber . ' needs your review';
                $line1 = 'A new order has been submitted and needs your review.';
                break;
            case self::ACTION_READY_FOR_APPROVAL:
                $subject = 'Order ' . $orderNumber . ' is ready for approval';
                $line1 = 'Order ' . $orderNumber . ' has been reviewed and is ready for your approval or rejection.';
                break;
            case self::ACTION_READY_FOR_PROCESSING:
                $subject = 'Order ' . $orderNumber . ' is ready for processing';
                $line1 = 'Order ' . $orderNumber . ' has been approved and is ready to be marked as in progress.';
                break;
            case self::ACTION_READY_FOR_DISPATCH:
                $subject = 'Order ' . $orderNumber . ' is ready for dispatch';
                $line1 = 'Order ' . $orderNumber . ' is in progress and is ready to be dispatched.';
                break;
            default:
                $subject = 'Order ' . $orderNumber . ' – action required';
                $line1 = 'Order ' . $orderNumber . ' requires your action.';
        }

        return (new MailMessage)
            ->subject($subject . ' - ' . config('app.name'))
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line($line1)
            ->line('Order: ' . $orderNumber)
            ->line('Facility: ' . $facilityName)
            ->action('View order', $showUrl)
            ->line('Please log in and take the required action.');
    }

    public function toArray($notifiable)
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'action' => $this->action,
        ];
    }
}
