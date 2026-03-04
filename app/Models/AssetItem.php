<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AssetHistory;
use Illuminate\Support\Facades\Log;

class AssetItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'asset_tag',
        'asset_name',
        'serial_number',
        'asset_category_id',
        'asset_type_id',
        'assignee_id',
        'status',
        'original_value',
    ];

    protected $casts = [
        'original_value' => 'decimal:2',
    ];

    // Relationships
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Assignee::class, 'assignee_id');
    }

    public function assetHistory(): HasMany
    {
        return $this->hasMany(AssetHistory::class);
    }

    public function maintenance(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class);
    }

    public function depreciation(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class);
    }

    // Constants
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_RETIRED = 'retired';
    const STATUS_DISPOSED = 'disposed';
    const STATUS_FUNCTIONING = 'functioning';
    const STATUS_NOT_FUNCTIONING = 'not_functioning';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_FUNCTIONING => 'Functioning',
            self::STATUS_NOT_FUNCTIONING => 'Not functioning',
            'in_use' => 'In Use',
            self::STATUS_MAINTENANCE => 'Maintenance',
            'pending_approval' => 'Pending Approval',
            self::STATUS_RETIRED => 'Retired',
            self::STATUS_DISPOSED => 'Disposed',
        ];
    }

    // Helper methods
    public function getAssetNumber(): string
    {
        return $this->asset->asset_number ?? 'Unknown';
    }

    public function getAssetLocation(): string
    {
        return $this->asset->assetLocation->name ?? 'Unknown';
    }

    public function getSubLocation(): string
    {
        return $this->asset->subLocation->name ?? 'Unknown';
    }

    public function getRegion(): string
    {
        return $this->asset->region->name ?? 'Unknown';
    }

    public function getFundSource(): string
    {
        return $this->asset->fundSource->name ?? 'Unknown';
    }

    public function getAcquisitionDate(): string
    {
        return $this->asset->acquisition_date?->format('Y-m-d') ?? 'Unknown';
    }

    public function isFunctioning(): bool
    {
        return $this->status === self::STATUS_FUNCTIONING;
    }

    /** @deprecated Use isFunctioning() */
    public function isActive(): bool
    {
        return $this->isFunctioning();
    }

    public function needsMaintenance(): bool
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }

    public function getCurrentValue(): float
    {
        $depreciation = $this->getDepreciationRecord();
        return $depreciation ? $depreciation->current_value : $this->original_value;
    }

    public function getDepreciationAmount(): float
    {
        $depreciation = $this->getDepreciationRecord();
        return $depreciation ? $depreciation->accumulated_depreciation : 0;
    }

    /**
     * Get or create the single depreciation record for this asset
     */
    public function getDepreciationRecord(): ?AssetDepreciation
    {
        return $this->depreciation()->latest()->first();
    }

    /**
     * Ensure only one depreciation record exists and update it
     */
    public function ensureSingleDepreciationRecord(): AssetDepreciation
    {
        // Get all depreciation records
        $depreciationRecords = $this->depreciation()->orderBy('created_at')->get();
        
        if ($depreciationRecords->count() > 1) {
            // Keep the first (oldest) record and delete the rest
            $mainRecord = $depreciationRecords->first();
            
            // Delete duplicate records
            $this->depreciation()
                ->where('id', '!=', $mainRecord->id)
                ->delete();
            
            Log::info("Cleaned up duplicate depreciation records for asset item {$this->id}, kept record {$mainRecord->id}");
            
            return $mainRecord;
        }
        
        if ($depreciationRecords->count() === 1) {
            return $depreciationRecords->first();
        }
        
        // No records exist, create one
        return $this->createInitialDepreciationRecord();
    }

    /**
     * Create initial depreciation record
     */
    private function createInitialDepreciationRecord(): AssetDepreciation
    {
        // Get configurable values from settings or use sensible defaults
        $usefulLifeYears = \App\Models\AssetDepreciationSetting::getValue('default_useful_life_years', 5);
        $salvageValue = \App\Models\AssetDepreciationSetting::getValue('default_salvage_value', 0);
        $method = \App\Models\AssetDepreciationSetting::getValue('default_method', AssetDepreciation::METHOD_STRAIGHT_LINE);
        
        // Check if asset has category-specific overrides
        if ($this->asset_category_id) {
            $categoryName = $this->assetCategory->name ?? null;
            
            if ($categoryName) {
                $categoryUsefulLife = \App\Models\AssetDepreciationSetting::getValue('useful_life_years', null, 'category_override', ['asset_category' => strtolower($categoryName)]);
                $categorySalvageValue = \App\Models\AssetDepreciationSetting::getValue('salvage_value', null, 'category_override', ['asset_category' => strtolower($categoryName)]);
                
                if ($categoryUsefulLife !== null) {
                    $usefulLifeYears = $categoryUsefulLife;
                }
                if ($categorySalvageValue !== null) {
                    $salvageValue = $categorySalvageValue;
                }
            }
        }
        
        $depreciableAmount = $this->original_value - $salvageValue;
        $annualDepreciation = $depreciableAmount / $usefulLifeYears;
        
        return $this->depreciation()->create([
            'original_value' => $this->original_value,
            'salvage_value' => $salvageValue,
            'useful_life_years' => $usefulLifeYears,
            'depreciation_method' => $method,
            'depreciation_rate' => $annualDepreciation,
            'current_value' => $this->original_value,
            'accumulated_depreciation' => 0,
            'depreciation_start_date' => now(),
            'last_calculated_date' => now(),
            'metadata' => [
                'created_by' => 'system',
                'created_at' => now()->toISOString(),
                'auto_created' => true,
                'source' => 'model_method',
                'config_source' => 'category_override',
                'useful_life_years' => $usefulLifeYears,
                'salvage_value' => $salvageValue,
            ],
        ]);
    }

    public function getMaintenanceHistory()
    {
        return $this->maintenance()->orderBy('created_at', 'desc')->get();
    }

    public function getUpcomingMaintenance()
    {
        return $this->maintenance()
            ->where('status', 'scheduled')
            ->where('scheduled_date', '>=', now())
            ->orderBy('scheduled_date')
            ->get();
    }

    public function getCompletedMaintenance()
    {
        return $this->maintenance()
            ->where('status', 'completed')
            ->orderBy('completed_date', 'desc')
            ->get();
    }

    public function scheduleMaintenance($type, $description, $scheduledDate, $cost = null)
    {
        return $this->maintenance()->create([
            'maintenance_type' => $type,
            'description' => $description,
            'scheduled_date' => $scheduledDate,
            'cost' => $cost,
            'status' => 'scheduled',
        ]);
    }

    public function completeMaintenance($maintenanceId, $notes = null)
    {
        $maintenance = $this->maintenance()->find($maintenanceId);
        if ($maintenance) {
            $maintenance->update([
                'status' => 'completed',
                'completed_date' => now(),
                'notes' => $notes,
            ]);
        }
        return $maintenance;
    }

    public function calculateDepreciation($method = 'straight_line', $usefulLifeYears = 5, $salvageValue = 0)
    {
        // Check if depreciation record already exists
        $depreciation = $this->depreciation()->latest()->first();
        
        if ($depreciation) {
            // Update existing record
            $depreciation->update([
                'original_value' => $this->original_value,
                'salvage_value' => $salvageValue,
                'useful_life_years' => $usefulLifeYears,
                'depreciation_method' => $method,
                'depreciation_start_date' => now(),
                'last_calculated_date' => now(),
            ]);
        } else {
            // Create new record only if none exists
            $depreciation = $this->depreciation()->create([
                'original_value' => $this->original_value,
                'salvage_value' => $salvageValue,
                'useful_life_years' => $usefulLifeYears,
                'depreciation_method' => $method,
                'current_value' => $this->original_value,
                'accumulated_depreciation' => 0,
                'depreciation_start_date' => now(),
                'last_calculated_date' => now(),
            ]);
        }

        // Calculate initial depreciation rate
        switch ($method) {
            case 'straight_line':
                $rate = ($this->original_value - $salvageValue) / $usefulLifeYears;
                break;
            case 'declining_balance':
                $rate = ($this->original_value - $salvageValue) * 0.2; // 20% declining balance
                break;
            default:
                $rate = ($this->original_value - $salvageValue) / $usefulLifeYears;
        }

        $depreciation->update([
            'depreciation_rate' => $rate,
        ]);

        return $depreciation;
    }

    /**
     * Create a history record for this asset item
     */
    public function createHistory(array $data): AssetHistory
    {
        return AssetHistory::create([
            'asset_item_id' => $this->id,
            'action' => $data['action'] ?? 'unknown',
            'action_type' => $data['action_type'] ?? 'general',
            'old_value' => $data['old_value'] ?? null,
            'new_value' => $data['new_value'] ?? null,
            'notes' => $data['notes'] ?? '',
            'performed_by' => $data['performed_by'] ?? null,
            'performed_at' => $data['performed_at'] ?? now(),
            'approval_id' => $data['approval_id'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
        ]);
    }

    // Scopes
    public function scopeFunctioning($query)
    {
        return $query->where('status', self::STATUS_FUNCTIONING);
    }

    /** @deprecated Use scopeFunctioning() */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_FUNCTIONING);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('asset_category_id', $categoryId);
    }

    public function scopeByType($query, $typeId)
    {
        return $query->where('asset_type_id', $typeId);
    }

    public function scopeByAssignee($query, $assigneeId)
    {
        return $query->where('assignee_id', $assigneeId);
    }

    public function scopeNeedsMaintenance($query)
    {
        return $query->where('status', self::STATUS_MAINTENANCE);
    }

    public function scopeByAsset($query, $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    public function scopeByValueRange($query, $minValue, $maxValue)
    {
        return $query->whereBetween('original_value', [$minValue, $maxValue]);
    }
}
