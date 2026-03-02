<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReportItem extends Model
{
    protected $fillable = [
        'inventory_report_id',
        'product_id',
        'warehouse_id',
        'uom',
        'batch_number',
        'expiry_date',
        'beginning_balance',
        'received_quantity',
        'issued_quantity',
        'other_quantity_out',
        'closing_balance',
        'total_closing_balance',
        'average_monthly_consumption',
        'quantity_in_pipeline',
        'stockout_days',
        'negative_adjustment',
        'positive_adjustment',
        'unit_cost',
        'total_cost'
    ];

    // 'beggining_balance','received_quantity','issued_quantity','closing_balance','total_closing_balance','average_monthly_consumption','months_of_stock','quantity_in_pipeline','unit_cost','total_cost'

    protected $casts = [
        'expiry_date' => 'date',
        'beginning_balance' => 'integer',
        'received_quantity' => 'integer',
        'issued_quantity' => 'integer',
        'other_quantity_out' => 'integer',
        'closing_balance' => 'integer',
        'total_closing_balance' => 'integer',
        'average_monthly_consumption' => 'integer',
        'quantity_in_pipeline' => 'integer',
        'stockout_days' => 'integer',
        'negative_adjustment' => 'integer',
        'positive_adjustment' => 'integer',
        'unit_cost' => 'float',
        'total_cost' => 'float'
    ];

    /**
     * Get the inventory report this item belongs to
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(InventoryReport::class, 'inventory_report_id');
    }

    /**
     * Get the product for this report item
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the warehouse for this report item
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
