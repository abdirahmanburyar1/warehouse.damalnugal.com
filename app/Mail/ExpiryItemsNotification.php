<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ExpiryItemsNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @var Collection */
    public $expiredItems;

    /** @var Collection */
    public $expiring6MonthsItems;

    /** @var Collection */
    public $expiring1YearItems;

    /** @var string */
    public $summaryText;

    public function __construct(
        Collection $expiredItems,
        Collection $expiring6MonthsItems,
        Collection $expiring1YearItems
    ) {
        $this->expiredItems = $expiredItems;
        $this->expiring6MonthsItems = $expiring6MonthsItems;
        $this->expiring1YearItems = $expiring1YearItems;

        $parts = [];
        if ($expiredItems->isNotEmpty()) {
            $parts[] = $expiredItems->count() . ' already expired';
        }
        if ($expiring6MonthsItems->isNotEmpty()) {
            $parts[] = $expiring6MonthsItems->count() . ' expiring in 6 months';
        }
        if ($expiring1YearItems->isNotEmpty()) {
            $parts[] = $expiring1YearItems->count() . ' expiring in 1 year';
        }
        $this->summaryText = implode(', ', $parts) ?: 'No items';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Expiry Items Notification - Warehouse Management',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.expiry-items-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
