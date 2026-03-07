<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Warehouse;
use App\Models\Facility;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\User;
use App\Traits\Auditable;

class Transfer extends Model
{
    use SoftDeletes, Auditable;
    protected $fillable = [
        'transferID',
        'transfer_date',
        'transfer_type',
        'from_warehouse_id',
        'to_warehouse_id',
        'from_facility_id',
        'to_facility_id',
        'created_by',  
        'status',
        'expected_date',
        'notes',
        'dispatched_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'delivered_by',
        'received_by',
        'processed_by',
        'dispatched_at',
        'delivered_at',
        'received_at',
        'reviewed_by',
        'reviewed_at',
        'processed_at',
    ];

    public static function generateTransferId()
    {
        $latestTransfer = self::latest()->first();
        $latestId = $latestTransfer ? (int) $latestTransfer->transferID : 0;
        $nextId = $latestId + 1;

        // Determine the minimum length based on the latest ID's length, default to 4
        $minLength = max(strlen((string)$latestId), 4);

        // Return zero-padded ID dynamically
        return str_pad($nextId, $minLength, '0', STR_PAD_LEFT);
    }
    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

     public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }
    
    public function fromFacility()
    {
        return $this->belongsTo(Facility::class, 'from_facility_id');
    }

    public function toFacility()
    {
        return $this->belongsTo(Facility::class, 'to_facility_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    public function items()
    {
        return $this->hasMany(TransferItem::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
    
    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function dispatchedBy()
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }
    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function dispatch()
    {
        return $this->hasMany(DispatchInfo::class);
    }

    public function backorders()
    {
        return $this->hasMany(BackOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Get the source name (warehouse or facility)
     */
    public function getSourceNameAttribute()
    {
        if ($this->from_warehouse_id) {
            return $this->fromWarehouse->name ?? 'Unknown Warehouse';
        }
        return $this->fromFacility->name ?? 'Unknown Facility';
    }
    
    /**
     * Get the destination name (warehouse or facility)
     */
    public function getDestinationNameAttribute()
    {
        if ($this->to_warehouse_id) {
            return $this->toWarehouse->name ?? 'Unknown Warehouse';
        }
        return $this->toFacility->name ?? 'Unknown Facility';
    }
    
    /**
     * Check if transfer is in a state that allows editing
     */
    public function isEditable()
    {
        return in_array($this->status, ['pending']);
    }
    
    /**
     * Check if transfer is in a state that allows deletion
     */
    public function isDeletable()
    {
        return in_array($this->status, ['pending']);
    }
    
    /**
     * Get total quantity of all items in the transfer
     */
    public function getTotalQuantityAttribute()
    {
        return $this->items->sum('quantity');
    }
    
    /**
     * Get total received quantity of all items in the transfer
     */
    public function getTotalReceivedQuantityAttribute()
    {
        return $this->items->sum('received_quantity');
    }
    
    /**
     * Get completion percentage
     */
    public function getCompletionPercentageAttribute()
    {
        $totalQuantity = $this->total_quantity;
        if ($totalQuantity == 0) return 0;
        
        return round(($this->total_received_quantity / $totalQuantity) * 100);
    }
    
    /**
     * Scope to filter transfers by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    
    /**
     * Scope to filter transfers by direction (in/out) for current user
     */
    public function scopeByDirection($query, $direction)
    {
        $user = auth()->user();
        $userFacilityId = $user->facility_id;
        $userWarehouseId = $user->warehouse_id;
        
        if ($direction === 'in') {
            return $query->where(function($q) use ($userFacilityId, $userWarehouseId) {
                $q->where('to_facility_id', $userFacilityId)
                  ->orWhere('to_warehouse_id', $userWarehouseId);
            });
        }
        
        if ($direction === 'out') {
            return $query->where(function($q) use ($userFacilityId, $userWarehouseId) {
                $q->where('from_facility_id', $userFacilityId)
                  ->orWhere('from_warehouse_id', $userWarehouseId);
            });
        }
        
        return $query;
    }

    public function getTransferIDAttribute($value)
    {
        return $value;
    }
}
