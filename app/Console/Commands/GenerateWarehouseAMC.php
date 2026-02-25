<?php

namespace App\Console\Commands;

use App\Models\EmailNotificationSetting;
use App\Models\IssueQuantityReport;
use App\Models\IssueQuantityItem;
use App\Models\ReorderLevel;
use App\Models\Product;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateWarehouseAMC extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warehouse:generate-amc {--month= : Specific month to process (YYYY-MM format)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate AMC (Average Monthly Consumption) and Reorder Levels based on last 3 months of issue quantity data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $monthArg = $this->option('month');
        $today = Carbon::now();

        if (!$monthArg) {
            $setting = EmailNotificationSetting::warehouseAmcSchedule();
            if (!$setting || !$setting->enabled) {
                $this->info('Warehouse AMC schedule is disabled or not configured.');
                return 0;
            }
            $config = $setting->config ?? [];
            $dayOfMonth = (int) ($config['day_of_month'] ?? 1);
            $time = $this->normalizeTime($config['time'] ?? '03:00');
            $currentTime = $today->format('H:i');
            if ($today->day != $dayOfMonth || $currentTime !== $time) {
                return 0;
            }
        }

        $this->info('Starting AMC and Reorder Level generation...');

        try {
            // Get the target month (default to last month)
            $targetMonth = $monthArg ?: Carbon::now()->subMonth()->format('Y-m');
            
            $this->info("Processing AMC for month: {$targetMonth}");

            // Get the last 3 months of data
            $months = $this->getLastThreeMonths($targetMonth);
            
            if (empty($months)) {
                $this->error('No issue quantity reports found for the last 3 months.');
                return 1;
            }

            $this->info('Found months: ' . implode(', ', $months));

            // Calculate AMC for each product
            $amcData = $this->calculateAMC($months);
            
            if (empty($amcData)) {
                $this->error('No AMC data calculated. Check if there are issue quantity items.');
                return 1;
            }

            // Update or create reorder levels
            $this->updateReorderLevels($amcData);

            $this->info('AMC and Reorder Level generation completed successfully!');
            return 0;

        } catch (\Exception $e) {
            $this->error('Error generating AMC: ' . $e->getMessage());
            Log::error('AMC generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Get the last 3 months of available data
     */
    private function getLastThreeMonths(string $targetMonth): array
    {
        $months = [];
        $currentMonth = Carbon::createFromFormat('Y-m', $targetMonth);
        
        // Start from the target month and go back 3 months
        for ($i = 0; $i < 3; $i++) {
            $monthToCheck = $currentMonth->copy()->subMonths($i)->format('Y-m');
            
            // Check if we have data for this month
            $report = IssueQuantityReport::where('month_year', $monthToCheck)->first();
            
            if ($report) {
                $months[] = $monthToCheck;
            } else {
                $this->warn("No report found for month: {$monthToCheck}");
            }
        }

        return $months;
    }

    /**
     * Calculate AMC for each product based on the last 3 months using efficient queries
     * Flow: Product -> IssueQuantityItem (after finding the month_year from its relationship with reports) -> ReorderLevel
     */
    private function calculateAMC(array $months): array
    {
        $amcData = [];

        // Debug: Check what months we're looking for
        $this->info("Looking for data in months: " . implode(', ', $months));

        // Debug: Check if we have reports for these months
        $reports = DB::table('issue_quantity_reports')
            ->whereIn('month_year', $months)
            ->select('id', 'month_year', 'total_quantity')
            ->get();
        
        $this->info("Found " . $reports->count() . " reports for the specified months");
        foreach ($reports as $report) {
            $this->line("  Report ID: {$report->id}, Month: {$report->month_year}, Total: {$report->total_quantity}");
        }

        // Debug: Check for the specific product mentioned
        $debugProduct = DB::table('products')
            ->where('name', 'like', '%Ampicillin%')
            ->first();
        
        if ($debugProduct) {
            $this->info("Found product: {$debugProduct->name} (ID: {$debugProduct->id})");
            
            // Check if this product has any issue quantity items
            $productItems = DB::table('issue_quantity_items as iqi')
                ->join('issue_quantity_reports as iqr', 'iqi.parent_id', '=', 'iqr.id')
                ->where('iqi.product_id', $debugProduct->id)
                ->whereIn('iqr.month_year', $months)
                ->select('iqi.id', 'iqi.quantity', 'iqr.month_year', 'iqr.id as report_id')
                ->get();
            
            $this->info("Found " . $productItems->count() . " items for Ampicillin in the specified months");
            foreach ($productItems as $item) {
                $this->line("  Item ID: {$item->id}, Quantity: {$item->quantity}, Month: {$item->month_year}, Report ID: {$item->report_id}");
            }
        }

        // Start from Product and traverse through IssueQuantityItem to get month_year from reports
        // Use a single query to get aggregated data following the specified flow
        $results = DB::table('products as p')
            ->leftJoin('issue_quantity_items as iqi', 'p.id', '=', 'iqi.product_id')
            ->leftJoin('issue_quantity_reports as iqr', 'iqi.parent_id', '=', 'iqr.id')
            ->select([
                'p.id as product_id',
                'p.name as product_name',
                'iqr.month_year',
                DB::raw('COALESCE(SUM(iqi.quantity), 0) as monthly_quantity')
            ])
            ->where('p.is_active', true)
            ->where(function($query) use ($months) {
                $query->whereIn('iqr.month_year', $months)
                      ->orWhereNull('iqr.month_year'); // Include products without any issue data
            })
            ->groupBy('p.id', 'p.name', 'iqr.month_year')
            ->get();

        $this->info("Main query returned " . $results->count() . " rows");

        // Group by product_id and calculate AMC
        $productData = [];
        foreach ($results as $row) {
            if (!isset($productData[$row->product_id])) {
                $productData[$row->product_id] = [
                    'product_id' => $row->product_id,
                    'product_name' => $row->product_name,
                    'monthly_quantities' => [],
                    'total_quantity' => 0,
                    'months_count' => 0
                ];
            }
            
            // Only count months that have actual data (not null month_year)
            if ($row->month_year) {
                // Store the monthly quantity for this product and month
                $productData[$row->product_id]['monthly_quantities'][$row->month_year] = (float)$row->monthly_quantity;
                $productData[$row->product_id]['total_quantity'] += (float)$row->monthly_quantity;
                $productData[$row->product_id]['months_count']++;
            }
        }

        // Calculate AMC for each product
        foreach ($productData as $productId => $data) {
            if ($data['months_count'] > 0) {
                // AMC = Total Quantity / Number of Months with Data
                $amc = $data['total_quantity'] / $data['months_count'];
                
                $amcData[$productId] = [
                    'product_id' => $productId,
                    'product_name' => $data['product_name'],
                    'amc' => round($amc, 2),
                    'months_used' => $data['months_count'],
                    'total_quantity' => $data['total_quantity'],
                    'monthly_breakdown' => $data['monthly_quantities']
                ];

                $this->line("Product: {$data['product_name']} - AMC: {$amc} (Total: {$data['total_quantity']}, Months: {$data['months_count']})");
                $this->line("  Monthly breakdown: " . json_encode($data['monthly_quantities']));
            } else {
                // Product has no issue data, but we still want to include it with AMC = 0
                $amcData[$productId] = [
                    'product_id' => $productId,
                    'product_name' => $data['product_name'],
                    'amc' => 0,
                    'months_used' => 0,
                    'total_quantity' => 0,
                    'monthly_breakdown' => []
                ];

                $this->line("Product: {$data['product_name']} - AMC: 0 (no issue data)");
            }
        }

        return $amcData;
    }

    /**
     * Update or create reorder levels with the calculated AMC using bulk operations
     * Creates reorder levels for ALL products, even those without issue quantity data
     */
    private function updateReorderLevels(array $amcData): void
    {
        // Get ALL products that should have reorder levels
        $allProducts = Product::select('id', 'name')
            ->where('is_active', true)
            ->get();

        $this->info("Total products to process: " . $allProducts->count());

        // Get existing reorder levels for all products
        $existingReorderLevels = ReorderLevel::pluck('product_id')->toArray();

        $toUpdate = [];
        $toCreate = [];
        $updated = 0;
        $created = 0;

        // Process each product
        foreach ($allProducts as $product) {
            $productId = $product->id;
            
            // Check if this product has AMC data
            $amcValue = 0;
            $hasAmcData = false;
            
            if (isset($amcData[$productId])) {
                $amcValue = $amcData[$productId]['amc'];
                $hasAmcData = true;
            }

            if (in_array($productId, $existingReorderLevels)) {
                // Update existing reorder level
                $toUpdate[] = [
                    'product_id' => $productId,
                    'amc' => $amcValue
                ];
                $updated++;
                
                if ($hasAmcData) {
                    $this->line("Updated: {$product->name} - AMC: {$amcValue}");
                } else {
                    $this->line("Updated: {$product->name} - AMC: 0 (no issue data)");
                }
            } else {
                // Create new reorder level
                $toCreate[] = [
                    'product_id' => $productId,
                    'amc' => $amcValue,
                    'lead_time' => 5, // Default lead time
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $created++;
                
                if ($hasAmcData) {
                    $this->line("Created: {$product->name} - AMC: {$amcValue}");
                } else {
                    $this->line("Created: {$product->name} - AMC: 0 (no issue data)");
                }
            }
        }

        // Bulk update existing records
        if (!empty($toUpdate)) {
            foreach ($toUpdate as $updateData) {
                $reorderLevel = ReorderLevel::where('product_id', $updateData['product_id'])->first();
                if ($reorderLevel) {
                    $reorderLevel->amc = $updateData['amc'];
                    $reorderLevel->save(); // This will trigger the boot method to calculate reorder_level
                }
            }
        }

        // Bulk insert new records
        if (!empty($toCreate)) {
            foreach ($toCreate as $createData) {
                $reorderLevel = new ReorderLevel();
                $reorderLevel->product_id = $createData['product_id'];
                $reorderLevel->amc = $createData['amc'];
                $reorderLevel->lead_time = $createData['lead_time'];
                $reorderLevel->save(); // This will trigger the boot method to calculate reorder_level
            }
        }

        $this->info("Reorder levels updated: {$updated}, created: {$created}");
        
        // Show summary
        $productsWithAmc = count(array_filter($amcData, function($data) {
            return $data['amc'] > 0;
        }));
        
        $this->info("Products with AMC data: {$productsWithAmc}");
        $this->info("Products without AMC data: " . ($allProducts->count() - $productsWithAmc));
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $parts = explode(':', $time);
            return sprintf('%02d:%02d', (int) $parts[0], (int) ($parts[1] ?? 0));
        }
        return '03:00';
    }
}
