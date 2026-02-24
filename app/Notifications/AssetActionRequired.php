<?php

namespace App\Notifications;

use App\Models\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssetActionRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = 10;

    public const ACTION_NEEDS_REVIEW = 'needs_review';
    public const ACTION_READY_FOR_APPROVAL = 'ready_for_approval';

    protected Asset $asset;
    protected string $action;

    /**
     * @param  Asset  $asset
     * @param  string  $action  One of ACTION_* constants (next step in workflow)
     */
    public function __construct(Asset $asset, string $action)
    {
        $this->asset = $asset->load(['fundSource', 'region']);
        $this->action = $action;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $assetNumber = $this->asset->asset_number ?? '#' . $this->asset->id;
        $path = route('assets.show', [$this->asset->id], false);
        $showUrl = str_starts_with($path, 'http') ? $path : rtrim(config('app.url'), '/') . (str_starts_with($path, '/') ? $path : '/' . $path);

        $subject = '';
        $line1 = '';
        switch ($this->action) {
            case self::ACTION_NEEDS_REVIEW:
                $subject = 'Asset ' . $assetNumber . ' needs your review';
                $line1 = 'A new asset has been submitted and needs your review.';
                break;
            case self::ACTION_READY_FOR_APPROVAL:
                $subject = 'Asset ' . $assetNumber . ' is ready for approval';
                $line1 = 'Asset ' . $assetNumber . ' has been reviewed and is ready for your approval or rejection.';
                break;
            default:
                $subject = 'Asset ' . $assetNumber . ' – action required';
                $line1 = 'Asset ' . $assetNumber . ' requires your action.';
        }

        return (new MailMessage)
            ->subject($subject . ' - ' . config('app.name'))
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line($line1)
            ->line('Asset: ' . $assetNumber)
            ->line('Acquisition date: ' . $this->asset->acquisition_date?->format('Y-m-d'))
            ->action('View asset', $showUrl)
            ->line('Please log in and take the required action.');
    }

    public function toArray($notifiable)
    {
        return [
            'asset_id' => $this->asset->id,
            'asset_number' => $this->asset->asset_number,
            'action' => $this->action,
        ];
    }
}
