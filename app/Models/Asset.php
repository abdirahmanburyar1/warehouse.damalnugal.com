<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_REVIEWED = 'reviewed';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_IN_USE = 'in_use';
    const STATUS_ACTIVE = 'active';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_RETIRED = 'retired';
    const STATUS_DISPOSED = 'disposed';
    const STATUS_IN_TRANSFER_PROCESS = 'in_transfer_process';

    protected $fillable = [
        'asset_number',
        'acquisition_date',
        'organization',
        'fund_source_id',
        'region_id',
        'district_id',
        'facility_id',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Temporarily disabled to prevent memory issues
        // static::addGlobalScope('organization', function (Builder $builder) {
        //     // Only apply organization filter if user is authenticated and has organization
        //     if (Auth::check() && Auth::user() && Auth::user()->organization) {
        //         $builder->where('organization', Auth::user()->organization);
        //     }
        // });
    }

    // Relationships
    public function assetItems(): HasMany
    {
        return $this->hasMany(AssetItem::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AssetDocument::class);
    }

    public function maintenance(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class);
    }

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function subLocation(): BelongsTo
    {
        return $this->belongsTo(SubLocation::class, 'sub_location_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Facility::class, 'facility_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function approvals(): HasMany
    {
        return $this->morphMany(AssetApproval::class, 'approvable');
    }

    // Helper methods
    public function getTotalItemCount(): int
    {
        return $this->assetItems()->count();
    }

    /**
     * Get the status of the asset based on approval workflow
     */
    public function getStatusAttribute()
    {
        // Check if asset is disposed
        if ($this->deleted_at) {
            return self::STATUS_DISPOSED;
        }
        
        // Check if asset is rejected
        if ($this->rejected_at) {
            return self::STATUS_REJECTED;
        }
        
        // Check if asset is approved
        if ($this->approved_at) {
            return self::STATUS_APPROVED;
        }
        
        // Check if asset is reviewed
        if ($this->reviewed_at) {
            return self::STATUS_REVIEWED;
        }
        
        // Check if asset is submitted for approval
        if ($this->submitted_at) {
            return self::STATUS_PENDING_APPROVAL;
        }
        
        // Default status
        return self::STATUS_DRAFT;
    }

    /**
     * Set the status of the asset by updating the appropriate fields
     */
    public function setStatus($status)
    {
        switch ($status) {
            case self::STATUS_PENDING_APPROVAL:
                $this->update([
                    'submitted_at' => now(),
                    'submitted_by' => Auth::id(),
                    'reviewed_at' => null,
                    'approved_at' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                ]);
                break;
                
            case self::STATUS_REVIEWED:
                $this->update([
                    'reviewed_at' => now(),
                    'reviewed_by' => Auth::id(),
                ]);
                break;
                
            case self::STATUS_APPROVED:
                $this->update([
                    'approved_at' => now(),
                    'approved_by' => Auth::id(),
                    'rejected_at' => null,
                    'rejection_reason' => null,
                ]);
                break;
                
            case self::STATUS_REJECTED:
                $this->update([
                    'rejected_at' => now(),
                    'rejected_by' => Auth::id(),
                ]);
                break;
                
            case self::STATUS_IN_USE:
            case self::STATUS_ACTIVE:
                // These statuses are typically set through other workflows
                // For now, we'll just ensure the asset is approved
                if (!$this->approved_at) {
                    $this->update([
                        'approved_at' => now(),
                        'approved_by' => Auth::id(),
                    ]);
                }
                break;
        }
    }

    /**
     * Scope for pending approval assets
     */
    public function scopePendingApproval($query)
    {
        return $query->whereNotNull('submitted_at')
                    ->whereNull('approved_at')
                    ->whereNull('rejected_at');
    }

    /**
     * Scope for approved assets
     */
    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_at');
    }

    /**
     * Scope for rejected assets
     */
    public function scopeRejected($query)
    {
        return $query->whereNotNull('rejected_at');
    }

    public function getFunctioningItemCount(): int
    {
        return $this->assetItems()->where('status', 'functioning')->count();
    }

    /** @deprecated Use getFunctioningItemCount() */
    public function getActiveItemCount(): int
    {
        return $this->getFunctioningItemCount();
    }

    public function getTotalValue(): float
    {
        return $this->assetItems()->sum('original_value');
    }

    public function getAverageItemValue(): float
    {
        $itemCount = $this->getTotalItemCount();
        return $itemCount > 0 ? $this->getTotalValue() / $itemCount : 0;
    }

    public function hasFunctioningItems(): bool
    {
        return $this->assetItems()->where('status', 'functioning')->exists();
    }

    /** @deprecated Use hasFunctioningItems() */
    public function hasActiveItems(): bool
    {
        return $this->hasFunctioningItems();
    }

    public function hasItemsNeedingMaintenance(): bool
    {
        return $this->assetItems()->where('status', 'maintenance')->exists();
    }

    public function getMaintenanceItems()
    {
        return $this->assetItems()->where('status', 'maintenance')->get();
    }

    public function getDocumentsByType($type = null)
    {
        $query = $this->documents();
        if ($type) {
            $query->where('document_type', $type);
        }
        return $query->get();
    }

    public function generateAssetNumber(): string
    {
        $prefix = 'ASSET';
        
        // Get all asset numbers that match the pattern and extract the highest number
        $assetNumbers = self::where('asset_number', 'like', $prefix . '-%')
            ->pluck('asset_number')
            ->map(function($assetNumber) use ($prefix) {
                $numberPart = preg_replace('/^' . preg_quote($prefix . '-', '/') . '/', '', $assetNumber);
                return (int) $numberPart;
            })
            ->filter()
            ->sort()
            ->values();
        
        if ($assetNumbers->isEmpty()) {
            return $prefix . '-001';
        }
        
        $nextNumber = $assetNumbers->last() + 1;
        
        // Use at least 3 digits, but more if needed for the next number
        $digitCount = max(3, strlen((string)$nextNumber));
        return $prefix . '-' . str_pad($nextNumber, $digitCount, '0', STR_PAD_LEFT);
    }

    /**
     * Create a history record for all associated asset items
     */
    public function createHistory(array $data): void
    {
        foreach ($this->assetItems()->get() as $assetItem) {
            $assetItem->createHistory($data);
        }
    }

    /**
     * Create a history record for all associated asset items (legacy method name)
     */
    public function createHistoryRecord(string $action, string $actionType, $oldValue = null, $newValue = null, string $notes = '', $approvalId = null): void
    {
        $data = [
            'action' => $action,
            'action_type' => $actionType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'notes' => $notes,
            'approval_id' => $approvalId,
        ];
        
        $this->createHistory($data);
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($asset) {
            if (empty($asset->asset_number)) {
                $asset->asset_number = $asset->generateAssetNumber();
            }
        });
    }
}
