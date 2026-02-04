<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'warehouse_id',
        'quantity_on_order',
        'qer',
        'quantity_to_release',
        'no_of_days',
        'uom',
    ];

    public function inventory_allocations(){
        return $this->hasMany(InventoryAllocation::class, 'order_item_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
