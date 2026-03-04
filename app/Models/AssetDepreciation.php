<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'asset_depreciation';

    protected $fillable = [
        'asset_item_id',
        'original_value',
        'salvage_value',
        'useful_life_years',
        'depreciation_method',
        'depreciation_rate',
        'current_value',
        'accumulated_depreciation',
        'depreciation_start_date',
        'last_calculated_date',
        'metadata',
    ];

    protected $casts = [
        'original_value' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'depreciation_rate' => 'decimal:4',
        'current_value' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'depreciation_start_date' => 'date',
        'last_calculated_date' => 'date',
        'metadata' => 'array',
    ];

    // Relationships
    public function assetItem(): BelongsTo
    {
        return $this->belongsTo(AssetItem::class);
    }

    // Constants
    const METHOD_STRAIGHT_LINE = 'straight_line';
    const METHOD_DECLINING_BALANCE = 'declining_balance';
    const METHOD_SUM_OF_YEARS = 'sum_of_years';

    public static function getDepreciationMethods(): array
    {
        return [
            self::METHOD_STRAIGHT_LINE => 'Straight Line',
            self::METHOD_DECLINING_BALANCE => 'Declining Balance',
            self::METHOD_SUM_OF_YEARS => 'Sum of Years',
        ];
    }

    // Helper methods
    public function getDepreciableAmount(): float
    {
        return $this->original_value - $this->salvage_value;
    }

    public function getAnnualDepreciation(): float
    {
        if ($this->depreciation_method === self::METHOD_STRAIGHT_LINE) {
            return $this->getDepreciableAmount() / $this->useful_life_years;
        } elseif ($this->depreciation_method === self::METHOD_DECLINING_BALANCE) {
            return $this->current_value * ($this->depreciation_rate / 100);
        } elseif ($this->depreciation_method === self::METHOD_SUM_OF_YEARS) {
            $remainingLife = $this->getRemainingLife();
            $sumOfYears = ($this->useful_life_years * ($this->useful_life_years + 1)) / 2;
            return $this->getDepreciableAmount() * ($remainingLife / $sumOfYears);
        }
        
        return 0;
    }

    public function getRemainingLife(): int
    {
        if (!$this->depreciation_start_date) {
            return $this->useful_life_years;
        }
        
        $yearsElapsed = $this->depreciation_start_date->diffInYears(now());
        return max(0, $this->useful_life_years - $yearsElapsed);
    }

    /**
     * Years elapsed since depreciation start (decimal, e.g. 2.9)
     */
    public function getYearsElapsedDecimal(): float
    {
        if (!$this->depreciation_start_date) {
            return 0;
        }
        $days = $this->depreciation_start_date->diffInDays(now());
        return round($days / 365.25, 1);
    }

    /**
     * Remaining useful life in years (decimal, e.g. 7.1)
     */
    public function getRemainingLifeDecimal(): float
    {
        $age = $this->getYearsElapsedDecimal();
        return max(0, round($this->useful_life_years - $age, 1));
    }

    /**
     * Year-to-date depreciation for current year: Annual × (Months/12)
     */
    public function getYtdDepreciation(): float
    {
        $annual = $this->getAnnualDepreciation();
        $monthsElapsed = (int) now()->format('n'); // 1-12
        return round($annual * ($monthsElapsed / 12), 2);
    }

    /**
     * Replacement year: Current Year + Remaining Life
     */
    public function getReplacementYear(): ?int
    {
        $remaining = $this->getRemainingLifeDecimal();
        if ($remaining <= 0) {
            return (int) now()->format('Y'); // Fully depreciated, replace now
        }
        return (int) (now()->format('Y') + $remaining);
    }

    public function getDepreciationPercentage(): float
    {
        if ($this->useful_life_years <= 0) {
            return 0;
        }
        
        $yearsElapsed = $this->depreciation_start_date ? $this->depreciation_start_date->diffInYears(now()) : 0;
        return min(100, ($yearsElapsed / $this->useful_life_years) * 100);
    }

    public function calculateDepreciation($asOfDate = null)
    {
        if (!$asOfDate) {
            $asOfDate = now();
        }

        if ($asOfDate < $this->depreciation_start_date) {
            return $this;
        }

        $yearsElapsed = $this->depreciation_start_date->diffInYears($asOfDate);
        
        if ($yearsElapsed >= $this->useful_life_years) {
            // Asset is fully depreciated
            $this->current_value = $this->salvage_value;
            $this->accumulated_depreciation = $this->getDepreciableAmount();
        } else {
            // Calculate depreciation for the period
            $annualDepreciation = $this->getAnnualDepreciation();
            $totalDepreciation = $annualDepreciation * $yearsElapsed;
            
            $this->accumulated_depreciation = min($totalDepreciation, $this->getDepreciableAmount());
            $this->current_value = $this->original_value - $this->accumulated_depreciation;
        }

        $this->last_calculated_date = $asOfDate;
        $this->save();

        return $this;
    }

    public function recalculateDepreciation()
    {
        return $this->calculateDepreciation(now());
    }

    public function getFormattedOriginalValue(): string
    {
        return '$' . number_format($this->original_value, 2);
    }

    public function getFormattedCurrentValue(): string
    {
        return '$' . number_format($this->current_value, 2);
    }

    public function getFormattedSalvageValue(): string
    {
        return '$' . number_format($this->salvage_value, 2);
    }

    public function getFormattedAccumulatedDepreciation(): string
    {
        return '$' . number_format($this->accumulated_depreciation, 2);
    }

    public function getFormattedAnnualDepreciation(): string
    {
        return '$' . number_format($this->getAnnualDepreciation(), 2);
    }

    public function isFullyDepreciated(): bool
    {
        return $this->current_value <= $this->salvage_value;
    }

    public function getAssetName(): string
    {
        return $this->assetItem->asset_name ?? 'Unknown Asset';
    }

    public function getAssetNumber(): string
    {
        return $this->assetItem->getAssetNumber() ?? 'Unknown';
    }

    // Scopes
    public function scopeByMethod($query, $method)
    {
        return $query->where('depreciation_method', $method);
    }

    public function scopeByAssetItem($query, $assetItemId)
    {
        return $query->where('asset_item_id', $assetItemId);
    }

    public function scopeFullyDepreciated($query)
    {
        return $query->whereRaw('current_value <= salvage_value');
    }

    public function scopeNotFullyDepreciated($query)
    {
        return $query->whereRaw('current_value > salvage_value');
    }

    public function scopeByValueRange($query, $minValue, $maxValue)
    {
        return $query->whereBetween('current_value', [$minValue, $maxValue]);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('depreciation_start_date', [$startDate, $endDate]);
    }

    public function scopeByUsefulLife($query, $minYears, $maxYears)
    {
        return $query->whereBetween('useful_life_years', [$minYears, $maxYears]);
    }
}
