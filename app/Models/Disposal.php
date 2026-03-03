<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Disposal extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($disposal) {
            $latestDisposal = static::latest()->first();
            $nextId = $latestDisposal ? intval(substr($latestDisposal->disposal_id, -7)) + 1 : 1;
            $disposal->disposal_id = str_pad($nextId, 7, '0', STR_PAD_LEFT);
        });
    }

    protected $fillable = [
        'disposal_id',
        'disposed_by',
        'disposed_at',
        'status',
        'source',
        'facility',
        'warehouse',
        'transfer_id',
        'order_id',
        'packing_list_id',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'back_order_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'disposed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Get the items for this disposal
     */
    public function items(): HasMany
    {
        return $this->hasMany(DisposalItem::class);
    }

    /**
     * Get the user who disposed the item
     */
    public function disposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by');
    }

    /**
     * Get the user who approved the disposal
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who reviewed the disposal
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the user who rejected the disposal
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the back order associated with this disposal
     */
    public function backOrder(): BelongsTo
    {
        return $this->belongsTo(BackOrder::class);
    }

    /**
     * Get the packing list associated with this disposal (when source is packing_list).
     */
    public function packingList(): BelongsTo
    {
        return $this->belongsTo(PackingList::class);
    }

    /**
     * Get the transfer associated with this disposal (when source is transfer).
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * Get the order associated with this disposal (when source is order).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
