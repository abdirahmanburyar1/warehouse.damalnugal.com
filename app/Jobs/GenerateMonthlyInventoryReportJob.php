<?php

namespace App\Jobs;

use App\Models\InventoryReport;
use App\Models\InventoryReportItem;
use App\Models\Product;
use App\Models\MonthlyQuantityReceived;
use App\Models\IssueQuantityReport;
use App\Models\ReceivedQuantity;
use App\Models\IssuedQuantity;
use App\Mail\MonthlyInventoryReportGenerated;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;

class GenerateMonthlyInventoryReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $monthYear;
    
    /**
     * Create a new job instance.
     */
    public function __construct($monthYear = null)
    {
        $this->monthYear = $monthYear ?? Carbon::now()->format('Y-m');
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            echo "Starting monthly inventory report generation for {$this->monthYear}\n";
            
            // Parse the month year
            $date = Carbon::createFromFormat('Y-m', $this->monthYear);
            $previousDate = $date->copy()->subMonth();
            
            echo "Processing date: {$date->format('Y-m')}\n";
            
            // Check if report already exists
            echo "Checking for existing report...\n";
            $existingReport = InventoryReport::where('month_year', $this->monthYear)->first();
            
            if ($existingReport) {
                echo "Report already exists. Updating...\n";
                // Delete existing items
                $existingReport->items()->delete();
                $report = $existingReport;
            } else {
                echo "Creating new report...\n";
                // Create new report
                $report = new InventoryReport();
                $report->month_year = $this->monthYear;
                $report->status = 'generated';
            }
            
            $report->generated_by = 1; // System user ID
            $report->generated_at = now();
            $report->save();
            
            echo "Report saved with ID: {$report->id}\n";
            
            // Generate report items for each product
            $itemsGenerated = $this->generateReportItems($report, $date, $previousDate);
            
            echo "Generated {$itemsGenerated} items\n";
            
            // Send email notification with report details - Skip for now to test
            // Mail::to('buryar313@gmail.com')->send(new MonthlyInventoryReportGenerated($report, $itemsGenerated));
            
            echo "Email notification skipped for testing\n";
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            
            // Send error email notification - Skip for now
            // Mail::to('buryar313@gmail.com')->send(new MonthlyInventoryReportGenerated(null, 0, $e->getMessage()));
            
            throw $e; // Re-throw to mark job as failed
        }
    }
    
    /**
     * Generate report items for all products
     */
    private function generateReportItems(InventoryReport $report, Carbon $date, Carbon $previousDate)
    {
        // This report depends on: (1) received quantity report, (2) issued quantity report, (3) warehouse AMC (for metrics).
        // Generate those first via Settings > Report Schedules or: report:monthly-received-quantities, report:issue-quantities, warehouse:generate-amc
        echo "Checking for required reports for {$date->format('Y-m')}...\n";
        
        $monthStr = $date->format('Y-m');
        $missing = [];

        $receivedReport = MonthlyQuantityReceived::where('month_year', $monthStr)->first();
        if (!$receivedReport) {
            $missing[] = 'received quantity report';
        }
        $issuedReport = IssueQuantityReport::where('month_year', $monthStr)->first();
        if (!$issuedReport) {
            $missing[] = 'issued quantity report';
        }
        if (!empty($missing)) {
            throw new \Exception(
                "Monthly inventory report for {$monthStr} requires the following to be generated first: " . implode(', ', $missing) . ". "
                . "Generate them from Settings > Report Schedules (Monthly received quantities, Issue quantities) or run the corresponding artisan commands, then run this again."
            );
        }
        
        echo "Found required reports. Proceeding with inventory report generation...\n";
        
        // Get received items from the generated report
        $receivedItems = $receivedReport->items()->with('product')->get();
        echo "Found {$receivedItems->count()} received items\n";
        
        // Get issued items from the generated report
        $issuedItems = $issuedReport->items()->with('product')->get();
        echo "Found {$issuedItems->count()} issued items\n";
        
        $startOfMonth = $date->copy()->startOfMonth()->startOfDay();
        $endOfMonth = $date->copy()->endOfMonth()->endOfDay();

        // One row per (product, warehouse, batch): same batch_no for received and issued on that row; different batches = different rows
        $receivedBatches = ReceivedQuantity::whereBetween('received_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('product_id, warehouse_id, COALESCE(NULLIF(TRIM(batch_number), ""), "N/A") as batch_key')
            ->groupBy('product_id', 'warehouse_id', 'batch_key')
            ->get();
        $issuedBatches = IssuedQuantity::whereBetween('issued_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('product_id, warehouse_id, COALESCE(NULLIF(TRIM(batch_number), ""), "N/A") as batch_key')
            ->groupBy('product_id', 'warehouse_id', 'batch_key')
            ->get();
        $allBatchKeys = $receivedBatches->map(fn ($r) => ['product_id' => $r->product_id, 'warehouse_id' => $r->warehouse_id, 'batch_key' => $r->batch_key])
            ->merge($issuedBatches->map(fn ($r) => ['product_id' => $r->product_id, 'warehouse_id' => $r->warehouse_id, 'batch_key' => $r->batch_key]))
            ->unique(fn ($item) => $item['product_id'] . '-' . $item['warehouse_id'] . '-' . $item['batch_key']);

        echo "Processing {$allBatchKeys->count()} unique product-warehouse-batch combinations\n";

        $itemsGenerated = 0;
        $processedCount = 0;
        foreach ($allBatchKeys as $row) {
            $processedCount++;
            if ($processedCount % 10 == 0) {
                echo "Processed {$processedCount}/{$allBatchKeys->count()} combinations\n";
            }
            $product = Product::find($row['product_id']);
            if (!$product) {
                continue;
            }
            $batchNumber = ($row['batch_key'] ?? '') === 'N/A' ? null : ($row['batch_key'] ?? null);
            $batchForDb = $batchNumber ?? 'N/A';
            if ($this->generateProductReportItemForBatch($report, $product, (int) $row['warehouse_id'], $batchNumber, $startOfMonth, $endOfMonth, $date, $previousDate)) {
                $itemsGenerated++;
            }
        }

        echo "Completed processing. Items generated: {$itemsGenerated}\n";
        return $itemsGenerated;
    }
    
    /**
     * Generate one report item for a specific product/warehouse/batch. Received and issued on this row
     * are for the same batch_no; different batches get different rows.
     */
    private function generateProductReportItemForBatch(InventoryReport $report, $product, int $warehouseId, ?string $batchNumber, Carbon $startOfMonth, Carbon $endOfMonth, Carbon $reportDate, Carbon $previousDate): bool
    {
        $batchForDb = $batchNumber !== null && $batchNumber !== '' ? $batchNumber : 'N/A';

        $receivedQtyQuery = ReceivedQuantity::whereBetween('received_at', [$startOfMonth, $endOfMonth])
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId);
        $issuedQtyQuery = IssuedQuantity::whereBetween('issued_date', [$startOfMonth, $endOfMonth])
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId);
        if ($batchNumber !== null && $batchNumber !== '') {
            $receivedQtyQuery->where('batch_number', $batchNumber);
            $issuedQtyQuery->where('batch_number', $batchNumber);
        } else {
            $receivedQtyQuery->where(function ($q) {
                $q->whereNull('batch_number')->orWhere('batch_number', '')->orWhereRaw("TRIM(batch_number) = ''");
            });
            $issuedQtyQuery->where(function ($q) {
                $q->whereNull('batch_number')->orWhere('batch_number', '')->orWhereRaw("TRIM(batch_number) = ''");
            });
        }
        $receivedQuantity = (int) $receivedQtyQuery->sum('quantity');
        $issuedQuantity = (int) $issuedQtyQuery->sum('quantity');
        $beginningBalance = $this->getPreviousMonthClosingBalance($product->id, $warehouseId, $previousDate->format('Y-m'), $batchNumber);
        $positiveAdjustment = 0;
        $negativeAdjustment = 0;
        $closingBalance = $beginningBalance + $receivedQuantity - $issuedQuantity - $negativeAdjustment + $positiveAdjustment;

        if ($beginningBalance > 0 || $receivedQuantity > 0 || $issuedQuantity > 0 || $closingBalance > 0) {
            $unitCost = 0;
            $costQtyReceived = ReceivedQuantity::whereBetween('received_at', [$startOfMonth, $endOfMonth])
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId);
            if ($batchNumber !== null && $batchNumber !== '') {
                $costQtyReceived->where('batch_number', $batchNumber);
            } else {
                $costQtyReceived->where(function ($q) {
                    $q->whereNull('batch_number')->orWhere('batch_number', '')->orWhereRaw("TRIM(batch_number) = ''");
                });
            }
            $receivedCostQty = $costQtyReceived->selectRaw('COALESCE(SUM(total_cost), 0) as total_cost, COALESCE(SUM(quantity), 0) as qty')->first();
            if ($receivedCostQty && $receivedCostQty->qty > 0 && $receivedCostQty->total_cost > 0) {
                $unitCost = (float) $receivedCostQty->total_cost / (float) $receivedCostQty->qty;
            }
            if ($unitCost == 0) {
                $costQtyIssued = IssuedQuantity::whereBetween('issued_date', [$startOfMonth, $endOfMonth])
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $warehouseId);
                if ($batchNumber !== null && $batchNumber !== '') {
                    $costQtyIssued->where('batch_number', $batchNumber);
                } else {
                    $costQtyIssued->where(function ($q) {
                        $q->whereNull('batch_number')->orWhere('batch_number', '')->orWhereRaw("TRIM(batch_number) = ''");
                    });
                }
                $issuedCostQty = $costQtyIssued->selectRaw('COALESCE(SUM(total_cost), 0) as total_cost, COALESCE(SUM(quantity), 0) as qty')->first();
                if ($issuedCostQty && $issuedCostQty->qty > 0 && $issuedCostQty->total_cost > 0) {
                    $unitCost = (float) $issuedCostQty->total_cost / (float) $issuedCostQty->qty;
                }
            }
            if ($unitCost == 0) {
                $prevItem = InventoryReportItem::whereHas('report', fn ($q) => $q->where('month_year', $previousDate->format('Y-m')))
                    ->where('product_id', $product->id)
                    // ->where('warehouse_id', $warehouseId)
                    ->when($batchNumber !== null && $batchNumber !== '', fn ($q) => $q->where('batch_number', $batchNumber), fn ($q) => $q->where(function ($q2) {
                        $q2->whereNull('batch_number')->orWhere('batch_number', '')->orWhere('batch_number', 'N/A');
                    }))
                    ->first();
                if ($prevItem && (float) ($prevItem->unit_cost ?? 0) > 0) {
                    $unitCost = (float) $prevItem->unit_cost;
                }
            }
            $totalCost = round(abs($closingBalance) * $unitCost, 2);
            // Item-level (product only, regardless of warehouse and batch): from IssuedQuantity/ReceivedQuantity dates
            $stockoutDays = $this->calculateStockoutDays($product->id, $startOfMonth, $endOfMonth);
            $expiryDate = now()->addYears(5)->format('Y-m-d');
            $firstReceived = ReceivedQuantity::whereBetween('received_at', [$startOfMonth, $endOfMonth])
                ->where('product_id', $product->id)
                // ->where('warehouse_id', $warehouseId)
                ->when($batchNumber !== null && $batchNumber !== '', fn ($q) => $q->where('batch_number', $batchNumber))
                ->orderBy('received_at')
                ->first();
            if ($firstReceived && $firstReceived->expiry_date) {
                $expiryDate = $firstReceived->expiry_date instanceof \DateTimeInterface ? $firstReceived->expiry_date->format('Y-m-d') : $firstReceived->expiry_date;
            } else {
                $firstIssued = IssuedQuantity::whereBetween('issued_date', [$startOfMonth, $endOfMonth])
                    ->where('product_id', $product->id)
                    // ->where('warehouse_id', $warehouseId)
                    ->when($batchNumber !== null && $batchNumber !== '', fn ($q) => $q->where('batch_number', $batchNumber))
                    ->orderBy('issued_date')
                    ->first();
                if ($firstIssued && $firstIssued->expiry_date) {
                    $expiryDate = $firstIssued->expiry_date instanceof \DateTimeInterface ? $firstIssued->expiry_date->format('Y-m-d') : $firstIssued->expiry_date;
                } elseif ($product->expiry_date) {
                    $expiryDate = $product->expiry_date instanceof \DateTimeInterface ? $product->expiry_date->format('Y-m-d') : $product->expiry_date;
                }
            }

            $attrs = [
                'inventory_report_id' => $report->id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'beginning_balance' => $beginningBalance,
                'received_quantity' => $receivedQuantity,
                'issued_quantity' => $issuedQuantity,
                'other_quantity_out' => 0,
                'closing_balance' => $closingBalance,
                'total_closing_balance' => $closingBalance,
                'average_monthly_consumption' => $issuedQuantity > 0 ? $issuedQuantity : 1,
                'quantity_in_pipeline' => 0,
            ];
            if (Schema::hasColumn('inventory_report_items', 'stockout_days')) {
                $attrs['stockout_days'] = $stockoutDays;
            }
            if (Schema::hasColumn('inventory_report_items', 'unit_cost')) {
                $attrs['unit_cost'] = round($unitCost, 2);
                $attrs['total_cost'] = $totalCost;
            }
            if (Schema::hasColumn('inventory_report_items', 'uom')) {
                $attrs['uom'] = $product->uom ?? 'pcs';
            }
            if (Schema::hasColumn('inventory_report_items', 'batch_number')) {
                $attrs['batch_number'] = $batchForDb;
            }
            if (Schema::hasColumn('inventory_report_items', 'expiry_date')) {
                $attrs['expiry_date'] = $expiryDate;
            }
            InventoryReportItem::create($attrs);
            return true;
        }
        return false;
    }
    
    /**
     * Calculate stockout days at item level (product only, regardless of warehouse and batch).
     * Uses IssuedQuantity and ReceivedQuantity: days between the last issue date in the month
     * and the next receive date. If no receive after last issue, counts days to end of month.
     */
    private function calculateStockoutDays(int $productId, Carbon $startOfMonth, Carbon $endOfMonth): int
    {
        $lastIssue = IssuedQuantity::where('product_id', $productId)
            ->whereBetween('issued_date', [$startOfMonth, $endOfMonth])
            ->orderByDesc('issued_date')
            ->value('issued_date');
        if (!$lastIssue) {
            return 0;
        }
        $lastIssueDate = $lastIssue instanceof \DateTimeInterface
            ? Carbon::parse($lastIssue)->startOfDay()
            : Carbon::parse($lastIssue)->startOfDay();
        $firstReceiveAfter = ReceivedQuantity::where('product_id', $productId)
            ->where('received_at', '>', $lastIssueDate->endOfDay())
            ->orderBy('received_at')
            ->value('received_at');
        if ($firstReceiveAfter) {
            $firstReceiveDate = $firstReceiveAfter instanceof \DateTimeInterface
                ? Carbon::parse($firstReceiveAfter)->startOfDay()
                : Carbon::parse($firstReceiveAfter)->startOfDay();
            return (int) $lastIssueDate->diffInDays($firstReceiveDate);
        }
        // No receive after last issue: count days from last issue to end of report month
        return (int) $lastIssueDate->diffInDays($endOfMonth->copy()->startOfDay());
    }

    /**
     * Get closing balance from previous month's report
     */
    private function getPreviousMonthClosingBalance($productId, $warehouseId, $monthYear, ?string $batchNumber = null)
    {
        $query = InventoryReportItem::whereHas('report', function ($q) use ($monthYear) {
            $q->where('month_year', $monthYear);
        })->where('product_id', $productId)->where('warehouse_id', $warehouseId);
        if ($batchNumber !== null && $batchNumber !== '') {
            $query->where('batch_number', $batchNumber);
        } else {
            $query->where(function ($q) {
                $q->whereNull('batch_number')->orWhere('batch_number', '')->orWhere('batch_number', 'N/A');
            });
        }
        $previousReportItem = $query->first();
        return $previousReportItem ? (int) $previousReportItem->closing_balance : 0;
    }
}
