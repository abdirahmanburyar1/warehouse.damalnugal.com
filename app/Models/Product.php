<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Category;
use App\Models\Dosage;
use App\Models\Inventory;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\SubCategory;
use App\Models\WarehouseAmc;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'productID',
        'name',
        'category_id',
        'dosage_id',
        // 'movement',
        'is_active',
        'tracert_type',
        'supply_class',
    ];

    protected $casts = [
        'tracert_type' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            // Find the highest productID in the database
            $maxProductId = self::max('productID');
            
            // If there are existing products, increment the highest productID
            if ($maxProductId) {
                $nextId = (int)$maxProductId + 1;
            } else {
                // Start from 1 if no products exist
                $nextId = 1;
            }
            
            // Format as 6-digit number with leading zeros
            $product->productID = str_pad($nextId, 6, '0', STR_PAD_LEFT);
        });
    }



    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    
    public function dosage()
    {
        return $this->belongsTo(Dosage::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class, 'sub_category_id');
    }

    /**
     * Get the supply items that contain this product.
     */
    public function supplyItems()
    {
        return $this->hasMany(SupplyItem::class);
    }

    /**
     * Get the supplies that contain this product.
     */
    public function supplies()
    {
        return $this->belongsToMany(Supply::class, 'supply_items')
            ->withPivot(['quantity', 'status'])
            ->withTimestamps();
    }

    // reorderLevel
    public function reorderLevel()
    {
        return $this->hasOne(ReorderLevel::class);
    }

    /**
     * Get the inventories for the product.
     */
    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function items(){
        return $this->hasMany(InventoryItem::class);
    }

    public function eligible(){
        return $this->hasMany(EligibleItem::class);
    }

    public function facilityInventories(){
        return $this->hasMany(FacilityInventory::class);
    }


    /**
     * Get the warehouse AMCs for this product.
     */
    public function warehouseAmcs()
    {
        return $this->hasMany(\App\Models\WarehouseAmc::class);
    }

    /**
     * Calculate AMC using exact 70% deviation screening formula
     * Formula: Find 3 consecutive months that pass ≤70% deviation threshold
     * Example: Jan=3000, Feb=2000, Mar=3000, Apr=6000, May=2500, Jun=300
     * Step 1: Take closest 3 months (Jun=300, May=2500, Apr=6000)
     * Step 2: Calculate average: (300+2500+6000)/3 = 2933
     * Step 3: Check each month: |300-2933|/2933*100 = 89.7% > 70% (FAILED)
     * Step 4: Reselect 3 months including passed ones: May=2500, Apr=6000, Mar=3000
     * Step 5: Calculate average: (2500+6000+3000)/3 = 3833
     * Step 6: Check each month: |2500-3833|/3833*100 = 34.8% ≤ 70% (PASSED)
     * Step 7: Final AMC = 3833
     */
    public function calculateAMC()
    {
        try {
            // Get all consumption values for the product from warehouse_amcs table
            $consumptionsWithMonth = $this->warehouseAmcs()
                ->where('quantity', '>', 0) // Only consider positive consumption values
                ->orderBy('month_year', 'desc') // Most recent first
                ->get(['month_year', 'quantity']);

            // If we have less than 3 values, return 0
            if ($consumptionsWithMonth->count() < 3) {
                return 0;
            }

            // Extract quantities and months
            $quantities = $consumptionsWithMonth->pluck('quantity')->values();
            $months = $consumptionsWithMonth->pluck('month_year')->values();
            
            // Apply the exact AMC screening formula step by step
            $selectedMonths = [];
            $foundValidGroup = false;
            $passedMonths = [];
            
            // Step 1: Start with the closest 3 months (first 3 in our sorted array)
            if ($quantities->count() >= 3) {
                // Step 2: Try to find 3 consecutive months that all pass the 70% threshold
                for ($startIndex = 0; $startIndex <= $quantities->count() - 3 && !$foundValidGroup; $startIndex++) {
                    $testGroup = [];
                    for ($i = 0; $i < 3; $i++) {
                        $testGroup[] = [
                            'month' => $months[$startIndex + $i],
                            'quantity' => $quantities[$startIndex + $i]
                        ];
                    }
                    
                    $groupSum = collect($testGroup)->sum('quantity');
                    $groupAverage = $groupSum / 3;
                    
                    // Step 3: Check if all months in this group pass the 70% threshold
                    $allPass = true;
                    $currentPassedMonths = [];
                    
                    foreach ($testGroup as $monthData) {
                        $quantity = $monthData['quantity'];
                        $deviation = abs($quantity - $groupAverage);
                        $percentage = $groupAverage > 0 ? ($deviation / $groupAverage) * 100 : 0;
                        
                        if ($percentage <= 70) {
                            $currentPassedMonths[] = $monthData;
                        } else {
                            $allPass = false;
                        }
                    }
                    
                    if ($allPass) {
                        // All 3 months passed, use this group
                        $selectedMonths = $testGroup;
                        $foundValidGroup = true;
                        break;
                    } else if (count($currentPassedMonths) > 0) {
                        // Some months passed, keep track of them for potential use
                        if (count($currentPassedMonths) > count($passedMonths)) {
                            $passedMonths = $currentPassedMonths;
                        }
                    }
                }
                
                // Step 4: If no valid group of 3 found, try to find a group including passed months
                if (!$foundValidGroup && count($passedMonths) > 0) {
                    // Try to find 3 months that work together, including the passed ones
                    for ($startIndex = 0; $startIndex <= $quantities->count() - 3; $startIndex++) {
                        $testGroup = [];
                        for ($i = 0; $i < 3; $i++) {
                            $testGroup[] = [
                                'month' => $months[$startIndex + $i],
                                'quantity' => $quantities[$startIndex + $i]
                            ];
                        }
                        
                        $groupSum = collect($testGroup)->sum('quantity');
                        $groupAverage = $groupSum / 3;
                        
                        // Check if this group passes
                        $allPass = true;
                        foreach ($testGroup as $monthData) {
                            $quantity = $monthData['quantity'];
                            $deviation = abs($quantity - $groupAverage);
                            $percentage = $groupAverage > 0 ? ($deviation / $groupAverage) * 100 : 0;
                            
                            if ($percentage > 70) {
                                $allPass = false;
                            }
                        }
                        
                        if ($allPass) {
                            $selectedMonths = $testGroup;
                            $foundValidGroup = true;
                            break;
                        }
                    }
                }
                
                // Step 5: If still no valid group, use the best available months
                if (!$foundValidGroup && count($selectedMonths) === 0) {
                    // Use the first 3 months as fallback
                    for ($i = 0; $i < 3; $i++) {
                        $selectedMonths[] = [
                            'month' => $months[$i],
                            'quantity' => $quantities[$i]
                        ];
                    }
                }
            }
            
            // Calculate final AMC
            if (count($selectedMonths) >= 3) {
                $amc = collect($selectedMonths)->avg('quantity');
                $result = round($amc, 2);
                return $result;
            } else {
                return 0;
            }

        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Calculate buffer stock: (Max AMC - AMC) × 3
     * Max AMC is the highest value from the selected months that passed 70% deviation screening
     */
    public function calculateBufferStock()
    {
        try {
            $amc = $this->calculateAMC();
            if ($amc == 0) {
                return 0;
            }

            // Get the selected months that passed screening (same logic as calculateAMC)
            $consumptionsWithMonth = $this->warehouseAmcs()
                ->where('quantity', '>', 0)
                ->orderBy('month_year', 'desc')
                ->get(['month_year', 'quantity']);

            if ($consumptionsWithMonth->count() < 3) {
                return 0;
            }

            // Use the same screening logic to get selected months
            $quantities = $consumptionsWithMonth->pluck('quantity')->values();
            $months = $consumptionsWithMonth->pluck('month_year')->values();
            
            $selectedMonths = [];
            $passedMonths = [];
            $failedMonths = [];
            
            // Initial selection: 3 most recent months
            for ($i = 0; $i < 3; $i++) {
                $selectedMonths[] = [
                    'month' => $months[$i],
                    'quantity' => $quantities[$i]
                ];
            }
            
            $attempt = 1;
            $maxAttempts = 10;
            
            while ($attempt <= $maxAttempts) {
                $average = collect($selectedMonths)->avg('quantity');
                $allPassed = true;
                $newPassedMonths = [];
                $newFailedMonths = [];
                
                foreach ($selectedMonths as $monthData) {
                    $quantity = $monthData['quantity'];
                    $deviation = abs($quantity - $average) / $average * 100;
                    
                    if ($deviation <= 70) {
                        $newPassedMonths[] = $monthData;
                    } else {
                        $newFailedMonths[] = $monthData;
                        $allPassed = false;
                    }
                }
                
                foreach ($newPassedMonths as $monthData) {
                    if (!collect($passedMonths)->contains('month', $monthData['month'])) {
                        $passedMonths[] = $monthData;
                    }
                }
                
                foreach ($newFailedMonths as $monthData) {
                    if (!collect($failedMonths)->contains('month', $monthData['month'])) {
                        $failedMonths[] = $monthData;
                    }
                }
                
                if ($allPassed) {
                    break;
                }
                
                if (count($passedMonths) >= 3) {
                    $selectedMonths = array_slice($passedMonths, 0, 3);
                    break;
                }
                
                $newSelection = [];
                foreach ($passedMonths as $monthData) {
                    $newSelection[] = $monthData;
                }
                
                $monthIndex = 0;
                while (count($newSelection) < 3 && $monthIndex < count($quantities)) {
                    $monthData = [
                        'month' => $months[$monthIndex],
                        'quantity' => $quantities[$monthIndex]
                    ];
                    
                    $alreadySelected = collect($newSelection)->contains('month', $monthData['month']);
                    $isFailed = collect($failedMonths)->contains('month', $monthData['month']);
                    
                    if (!$alreadySelected && !$isFailed) {
                        $newSelection[] = $monthData;
                    }
                    
                    $monthIndex++;
                }
                
                $selectedMonths = $newSelection;
                $attempt++;
            }
            
            if (count($selectedMonths) >= 3) {
                // Find the maximum value from selected months
                $maxAMC = max(array_column($selectedMonths, 'quantity'));
                
                // Calculate buffer stock: (Max AMC - AMC) × 3
                $bufferStock = ($maxAMC - $amc) * 3;
                return round(max(0, $bufferStock), 2);
            }
            
            return 0;
            
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Calculate reorder level: (AMC × 3) + Buffer Stock
     */
    public function calculateReorderLevel()
    {
        try {
            $amc = $this->calculateAMC();
            if ($amc == 0) {
                return 0;
            }
            
            $bufferStock = $this->calculateBufferStock();
            
            // Calculate reorder level: (AMC × 3) + Buffer Stock
            $reorderLevel = ($amc * 3) + $bufferStock;
            return round($reorderLevel, 2);
            
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Optimized method to calculate AMC, Buffer Stock, and Reorder Level in one go
     * This avoids duplicate database queries and improves performance
     */
    public function calculateInventoryMetrics()
    {
        try {
            // Get all consumption values for the product from warehouse_amcs table
            $consumptionsWithMonth = $this->warehouseAmcs()
                ->where('quantity', '>', 0) // Only consider positive consumption values
                ->orderBy('month_year', 'desc') // Most recent first
                ->get(['month_year', 'quantity']);

            // If we have less than 3 values, return default values
            if ($consumptionsWithMonth->count() < 3) {
                return [
                    'amc' => 0,
                    'buffer_stock' => 0,
                    'reorder_level' => 0
                ];
            }

            // Extract quantities and months
            $quantities = $consumptionsWithMonth->pluck('quantity')->values();
            $months = $consumptionsWithMonth->pluck('month_year')->values();
            
            // Start with the 3 most recent months
            $selectedMonths = [];
            $passedMonths = [];
            $failedMonths = [];
            
            // Initial selection: 3 most recent months
            for ($i = 0; $i < 3; $i++) {
                $selectedMonths[] = [
                    'month' => $months[$i],
                    'quantity' => $quantities[$i]
                ];
            }
            
            $attempt = 1;
            $maxAttempts = 10; // Prevent infinite loops
            
            while ($attempt <= $maxAttempts) {
                // Calculate average of selected months
                $average = collect($selectedMonths)->avg('quantity');
                
                // Check each month's deviation
                $allPassed = true;
                $newPassedMonths = [];
                $newFailedMonths = [];
                
                foreach ($selectedMonths as $monthData) {
                    $quantity = $monthData['quantity'];
                    $deviation = abs($quantity - $average) / $average * 100;
                    
                    if ($deviation <= 70) {
                        // Month passed screening
                        $newPassedMonths[] = $monthData;
                    } else {
                        // Month failed screening
                        $newFailedMonths[] = $monthData;
                        $allPassed = false;
                    }
                }
                
                // Add newly passed months to the global passed list
                foreach ($newPassedMonths as $monthData) {
                    if (!collect($passedMonths)->contains('month', $monthData['month'])) {
                        $passedMonths[] = $monthData;
                    }
                }
                
                // Add newly failed months to the global failed list
                foreach ($newFailedMonths as $monthData) {
                    if (!collect($failedMonths)->contains('month', $monthData['month'])) {
                        $failedMonths[] = $monthData;
                    }
                }
                
                // If all months passed, we're done
                if ($allPassed) {
                    break;
                }
                
                // If we have 3 or more passed months, use them
                if (count($passedMonths) >= 3) {
                    $selectedMonths = array_slice($passedMonths, 0, 3);
                    break;
                }
                
                // Need to reselect months including passed ones
                $newSelection = [];
                
                // First, include all passed months
                foreach ($passedMonths as $monthData) {
                    $newSelection[] = $monthData;
                }
                
                // Then add more months from the original list until we have 3
                $monthIndex = 0;
                while (count($newSelection) < 3 && $monthIndex < count($quantities)) {
                    $monthData = [
                        'month' => $months[$monthIndex],
                        'quantity' => $quantities[$monthIndex]
                    ];
                    
                    // Only add if not already in selection and not in failed months
                    $alreadySelected = collect($newSelection)->contains('month', $monthData['month']);
                    $isFailed = collect($failedMonths)->contains('month', $monthData['month']);
                    
                    if (!$alreadySelected && !$isFailed) {
                        $newSelection[] = $monthData;
                    }
                    
                    $monthIndex++;
                }
                
                // Update selected months for next iteration
                $selectedMonths = $newSelection;
                $attempt++;
            }
            
            // Calculate final metrics
            if (count($selectedMonths) >= 3) {
                $amc = collect($selectedMonths)->avg('quantity');
                $amc = round($amc, 2);
                
                // Find the maximum value from selected months
                $maxAMC = max(array_column($selectedMonths, 'quantity'));
                
                // Calculate buffer stock: (Max AMC - AMC) × 3
                $bufferStock = ($maxAMC - $amc) * 3;
                $bufferStock = round(max(0, $bufferStock), 2);
                
                // Calculate reorder level: (AMC × 3) + Buffer Stock
                $reorderLevel = ($amc * 3) + $bufferStock;
                $reorderLevel = round($reorderLevel, 2);
                
                return [
                    'amc' => $amc,
                    'buffer_stock' => $bufferStock,
                    'reorder_level' => $reorderLevel
                ];
            } else {
                return [
                    'amc' => 0,
                    'buffer_stock' => 0,
                    'reorder_level' => 0
                ];
            }

        } catch (\Exception $e) {
            return [
                'amc' => 0,
                'buffer_stock' => 0,
                'reorder_level' => 0
            ];
        }
    }

    /**
     * Get the inventory structure for frontend
     */
    public function getInventoryStructureAttribute()
    {
        // Get inventory items directly for this product
        $inventoryItems = $this->items;
        
        // Calculate all inventory metrics in one optimized call
        $metrics = $this->calculateInventoryMetrics();
        
        // Calculate the current status based on total quantity and reorder level
        // EXACTLY matching the frontend getInventoryStatus logic
        $totalQuantity = $inventoryItems->sum('quantity');
        $reorderLevel = $metrics['reorder_level'];
        
        $status = 'in_stock'; // default
        
        // Check if completely out of stock first
        if ($totalQuantity <= 0) {
            $status = 'out_of_stock';
        } elseif ($reorderLevel <= 0) {
            // No reorder level set, default to in stock
            $status = 'in_stock';
        } else {
            // Calculate the low stock threshold (reorder level + 30%)
            $lowStockThreshold = $reorderLevel * 1.3;
            
            if ($totalQuantity <= $reorderLevel) {
                // Items at or below reorder level (1 to 9,000 in your example)
                $status = 'low_stock_reorder_level';
            } elseif ($totalQuantity <= $lowStockThreshold) {
                // Items between reorder level and reorder level + 30% (9,001 to 11,700 in your example)
                $status = 'low_stock';
            } else {
                // Items above reorder level + 30% (above 11,700 in your example)
                $status = 'in_stock';
            }
        }
        
        return [
            'id' => $this->id,
            'product_id' => $this->id,
            'items' => $inventoryItems, // This will be empty array if no items exist
            'amc' => $metrics['amc'],
            'buffer_stock' => $metrics['buffer_stock'],
            'reorder_level' => $metrics['reorder_level'],
            'status' => $status, // Add calculated status
            'product' => [
                'id' => $this->id,
                'name' => $this->name,
                'uom' => $inventoryItems->first()->uom,
                'category' => [
                    'id' => $this->category->id ?? null,
                    'name' => $this->category->name ?? null
                ],
                'dosage' => [
                    'id' => $this->dosage->id ?? null,
                    'name' => $this->dosage->name ?? null
                ]
            ]
        ];
    }


}
