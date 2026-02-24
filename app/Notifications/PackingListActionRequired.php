<?php

namespace App\Notifications;

use App\Models\PackingList;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PackingListActionRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = 10;

    public const ACTION_NEEDS_REVIEW = 'needs_review';
    public const ACTION_READY_FOR_APPROVAL = 'ready_for_approval';

    protected PackingList $packingList;
    protected string $action;

    /**
     * @param  PackingList  $packingList
     * @param  string  $action  self::ACTION_NEEDS_REVIEW or self::ACTION_READY_FOR_APPROVAL
     */
    public function __construct(PackingList $packingList, string $action)
    {
        $this->packingList = $packingList->load('purchaseOrder.supplier');
        $this->action = $action;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $plNumber = $this->packingList->packing_list_number ?? '#' . $this->packingList->id;
        $supplierName = $this->packingList->purchaseOrder?->supplier?->name ?? 'N/A';
        $editUrl = rtrim(config('app.url'), '/') . '/' . ltrim(route('supplies.packing-list.edit', ['id' => $this->packingList->id], false), '/');

        if ($this->action === self::ACTION_NEEDS_REVIEW) {
            $subject = 'Packing list ' . $plNumber . ' needs your review';
            $line1 = 'A packing list has been submitted and needs your review.';
        } else {
            $subject = 'Packing list ' . $plNumber . ' is ready for approval';
            $line1 = 'Packing list ' . $plNumber . ' has been reviewed and is ready for your approval or rejection.';
        }

        return (new MailMessage)
            ->subject($subject . ' - ' . config('app.name'))
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line($line1)
            ->line('Packing list: ' . $plNumber)
            ->line('Supplier: ' . $supplierName)
            ->action('View packing list', $editUrl)
            ->line('Please log in and take the required action.');
    }

    public function toArray($notifiable)
    {
        return [
            'packing_list_id' => $this->packingList->id,
            'packing_list_number' => $this->packingList->packing_list_number,
            'action' => $this->action,
        ];
    }
}
