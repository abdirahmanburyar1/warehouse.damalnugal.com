<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use App\Events\OrderEvent;
use App\Models\Facility;
use App\Models\User;
use App\Models\OrderItem;
use App\Models\Approval;
use App\Models\Warehouse;
use App\Traits\Auditable;

class Order extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'facility_id',
        'order_type',
        'order_number',
        'status',
        'notes',
        'order_date',
        'expected_date',
        'dispatched_by',
        'created_by',
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
    
    public function dispatch(){
        return $this->hasMany(DispatchInfo::class);
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
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
}
