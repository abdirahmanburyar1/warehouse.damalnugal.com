<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseOrderActionRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = 10;

    public const ACTION_NEEDS_REVIEW = 'needs_review';
    public const ACTION_READY_FOR_APPROVAL = 'ready_for_approval';

    protected PurchaseOrder $po;
    protected string $action;

    /**
     * Create a new notification instance.
     *
     * @param  PurchaseOrder  $po
     * @param  string  $action  self::ACTION_NEEDS_REVIEW or self::ACTION_READY_FOR_APPROVAL
     */
    public function __construct(PurchaseOrder $po, string $action)
    {
        $this->po = $po->load('supplier');
        $this->action = $action;
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
        $poNumber = $this->po->po_number ?? '#' . $this->po->id;
        $supplierName = $this->po->supplier?->name ?? 'N/A';
        // Use APP_URL from .env so email links point to your public site, not localhost
        $editUrl = rtrim(config('app.url'), '/') . '/' . ltrim(route('supplies.editPO', ['id' => $this->po->id], false), '/');

        if ($this->action === self::ACTION_NEEDS_REVIEW) {
            $subject = 'Purchase order ' . $poNumber . ' needs your review';
            $line1 = 'A new purchase order has been submitted and needs your review.';
        } else {
            $subject = 'Purchase order ' . $poNumber . ' is ready for approval';
            $line1 = 'Purchase order ' . $poNumber . ' has been reviewed and is ready for your approval or rejection.';
        }

        return (new MailMessage)
            ->subject($subject . ' - ' . config('app.name'))
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line($line1)
            ->line('Purchase order: ' . $poNumber)
            ->line('Supplier: ' . $supplierName)
            ->action('View purchase order', $editUrl)
            ->line('Please log in and take the required action.');
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
            'purchase_order_id' => $this->po->id,
            'po_number' => $this->po->po_number,
            'action' => $this->action,
        ];
    }
}
