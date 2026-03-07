<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class FacilityMonthlyReport extends Model
{
    use HasFactory, Auditable;

    protected $table = 'facility_monthly_reports';

    protected $fillable = [
        'facility_id',
        'report_period',
        'status',
        'comments',
        'submitted_at',
        'submitted_by',
        'reviewed_at',
        'reviewed_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Get the facility that owns the report
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get all report items for this report
     */
    public function items(): HasMany
    {
        return $this->hasMany(FacilityMonthlyReportItem::class, 'parent_id');
    }

    /**
     * Get the user who submitted the report
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the user who reviewed the report
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the user who approved the report
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who rejected the report
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get formatted report period (e.g., "June 2025")
     */
    public function getReportPeriodFormattedAttribute(): string
    {
        [$year, $month] = explode('-', $this->report_period);
        
        $months = [
            '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
            '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
            '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
        ];
        
        return $months[$month] . ' ' . $year;
    }

    /**
     * Get year from report_period
     */
    public function getYearAttribute(): int
    {
        return (int) explode('-', $this->report_period)[0];
    }

    /**
     * Get month from report_period
     */
    public function getMonthAttribute(): int
    {
        return (int) explode('-', $this->report_period)[1];
    }

    /**
     * Get formatted month name
     */
    public function getMonthNameAttribute(): string
    {
        $month = explode('-', $this->report_period)[1];
        
        $months = [
            '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
            '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
            '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
        ];
        
        return $months[$month] ?? 'Unknown';
    }

    /**
     * Get total items count
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Get total opening balance across all items
     */
    public function getTotalOpeningBalanceAttribute(): float
    {
        return $this->items()->sum('opening_balance');
    }

    /**
     * Get total stock received across all items
     */
    public function getTotalStockReceivedAttribute(): float
    {
        return $this->items()->sum('stock_received');
    }

    /**
     * Get total stock issued across all items
     */
    public function getTotalStockIssuedAttribute(): float
    {
        return $this->items()->sum('stock_issued');
    }

    /**
     * Get total closing balance across all items
     */
    public function getTotalClosingBalanceAttribute(): float
    {
        return $this->items()->sum('closing_balance');
    }

    /**
     * Check if report can be edited
     */
    public function canEdit(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if report can be submitted
     */
    public function canSubmit(): bool
    {
        return $this->status === 'draft' && $this->items()->count() > 0;
    }

    /**
     * Check if report can be approved
     */
    public function canApprove(): bool
    {
        return $this->status === 'reviewed';
    }

    /**
     * Submit the report
     */
    public function submit(): void
    {
        if ($this->canSubmit()) {
            $this->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'submitted_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Approve the report
     */
    public function approve(): void
    {
        if ($this->canApprove()) {
            $this->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Scope for specific facility
     */
    public function scopeForFacility($query, $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Scope for specific period
     */
    public function scopeForPeriod($query, $year, $month)
    {
        return $query->where('report_period', $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT));
    }

    /**
     * Scope for status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for current user's facility
     */
    public function scopeForCurrentFacility($query)
    {
        return $query->where('facility_id', auth()->user()->facility_id);
    }

    /**
     * Scope for recent reports (last 12 months)
     */
    public function scopeRecent($query)
    {
        $twelveMonthsAgo = now()->subMonths(12);
        return $query->where(function ($q) use ($twelveMonthsAgo) {
            $q->where('report_period', '>', $twelveMonthsAgo->format('Y-m'))
              ->orWhere(function ($q2) use ($twelveMonthsAgo) {
                  $q2->where('report_period', $twelveMonthsAgo->format('Y-m'));
              });
        });
    }
}
