<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{

    protected $fillable = [
        'name',
        'email',
        'user_id',
        'district',
        'handled_by',
        'region',
        'phone',
        'address',
        'facility_type',
        'has_cold_storage',
        'is_active',
    ];

    public function inventories()
    {
        return $this->hasMany(FacilityInventory::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function handledby(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * Get the monthly reports for this facility
     */
    public function monthlyReports(): HasMany
    {
        return $this->hasMany(FacilityMonthlyReport::class);
    }

    /**
     * Get products eligible for this facility type (from eligible_items).
     * Used for LMIS report generation to create empty rows for products with no movements.
     */
    public function eligibleProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'eligible_items', 'facility_type', 'product_id', 'facility_type', 'id')
            ->where('products.is_active', true)
            ->orderBy('products.name');
    }
}
