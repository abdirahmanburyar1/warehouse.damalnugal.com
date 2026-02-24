<?php

namespace App\Notifications;

use App\Models\Transfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransferActionRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = 10;

    public const ACTION_NEEDS_REVIEW = 'needs_review';
    public const ACTION_READY_FOR_APPROVAL = 'ready_for_approval';
    public const ACTION_READY_FOR_PROCESSING = 'ready_for_processing';
    public const ACTION_READY_FOR_DISPATCH = 'ready_for_dispatch';
    public const ACTION_READY_FOR_DELIVERY = 'ready_for_delivery';
    public const ACTION_READY_FOR_RECEIVE = 'ready_for_receive';

    protected Transfer $transfer;
    protected string $action;

    /**
     * @param  Transfer  $transfer
     * @param  string  $action  One of ACTION_* constants (next step in workflow)
     */
    public function __construct(Transfer $transfer, string $action)
    {
        $this->transfer = $transfer->load(['fromWarehouse', 'toWarehouse', 'fromFacility', 'toFacility']);
        $this->action = $action;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $transferId = $this->transfer->transferID ?? '#' . $this->transfer->id;
        $path = route('transfers.show', [$this->transfer->id], false);
        $showUrl = str_starts_with($path, 'http') ? $path : rtrim(config('app.url'), '/') . (str_starts_with($path, '/') ? $path : '/' . $path);

        $sourceName = $this->transfer->from_warehouse_id
            ? ($this->transfer->fromWarehouse?->name ?? 'N/A')
            : ($this->transfer->fromFacility?->name ?? 'N/A');
        $destName = $this->transfer->to_warehouse_id
            ? ($this->transfer->toWarehouse?->name ?? 'N/A')
            : ($this->transfer->toFacility?->name ?? 'N/A');

        $subject = '';
        $line1 = '';
        switch ($this->action) {
            case self::ACTION_NEEDS_REVIEW:
                $subject = 'Transfer ' . $transferId . ' needs your review';
                $line1 = 'A new transfer has been submitted and needs your review.';
                break;
            case self::ACTION_READY_FOR_APPROVAL:
                $subject = 'Transfer ' . $transferId . ' is ready for approval';
                $line1 = 'Transfer ' . $transferId . ' has been reviewed and is ready for your approval or rejection.';
                break;
            case self::ACTION_READY_FOR_PROCESSING:
                $subject = 'Transfer ' . $transferId . ' is ready for processing';
                $line1 = 'Transfer ' . $transferId . ' has been approved and is ready to be marked as in progress.';
                break;
            case self::ACTION_READY_FOR_DISPATCH:
                $subject = 'Transfer ' . $transferId . ' is ready for dispatch';
                $line1 = 'Transfer ' . $transferId . ' is in progress and is ready to be dispatched.';
                break;
            case self::ACTION_READY_FOR_DELIVERY:
                $subject = 'Transfer ' . $transferId . ' is ready for delivery confirmation';
                $line1 = 'Transfer ' . $transferId . ' has been dispatched and is ready to be marked as delivered.';
                break;
            case self::ACTION_READY_FOR_RECEIVE:
                $subject = 'Transfer ' . $transferId . ' is ready to receive';
                $line1 = 'Transfer ' . $transferId . ' has been delivered and is ready to be received at destination.';
                break;
            default:
                $subject = 'Transfer ' . $transferId . ' – action required';
                $line1 = 'Transfer ' . $transferId . ' requires your action.';
        }

        return (new MailMessage)
            ->subject($subject . ' - ' . config('app.name'))
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line($line1)
            ->line('Transfer: ' . $transferId)
            ->line('From: ' . $sourceName)
            ->line('To: ' . $destName)
            ->action('View transfer', $showUrl)
            ->line('Please log in and take the required action.');
    }

    public function toArray($notifiable)
    {
        return [
            'transfer_id' => $this->transfer->id,
            'transferID' => $this->transfer->transferID,
            'action' => $this->action,
        ];
    }
}
