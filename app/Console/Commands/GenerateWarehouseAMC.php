<?php

namespace App\Console\Commands;

use App\Models\EmailNotificationSetting;
use App\Models\IssueQuantityReport;
use App\Models\IssueQuantityItem;
use App\Models\ReorderLevel;
use App\Models\Product;
use App\Models\WarehouseAmc;
use App\Services\WarehouseAmcCalculationService;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
    protected $description = 'Generate AMC (Average Monthly Consumption) with 70% deviation screening and Reorder Levels from issue quantity data';

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

            // Get up to 12 months of data (needed for 70% deviation screening reselection)
            $months = $this->getLastMonthsWithReports($targetMonth, 12);

            if (empty($months)) {
                $this->error('No issue quantity reports found for the target period.');
                return 1;
            }

            $this->info('Found months: ' . implode(', ', $months));

            // Calculate AMC for each product using 70% deviation screening
            $amcData = $this->calculateAMCWithScreening($months);

            if (empty($amcData)) {
                $this->error('No AMC data calculated. Check if there are issue quantity items.');
                return 1;
            }

            // Update or create reorder levels
            $this->updateReorderLevels($amcData);

            // Sync monthly consumption to warehouse_amcs (for Warehouse AMC report / export)
            $this->syncWarehouseAmcs($amcData, $months);

            $this->info('AMC and Reorder Level generation completed successfully!');
            return 0;

        } catch (\Exception $e) {
            $this->error('Error generating AMC: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get the last N months that have issue quantity reports (newest first)
     */
    private function getLastMonthsWithReports(string $targetMonth, int $limit = 12): array
    {
        $months = [];
        $currentMonth = Carbon::createFromFormat('Y-m', $targetMonth);

        for ($i = 0; $i < $limit; $i++) {
            $monthToCheck = $currentMonth->copy()->subMonths($i)->format('Y-m');
            $report = IssueQuantityReport::where('month_year', $monthToCheck)->first();
            if ($report) {
                $months[] = $monthToCheck;
            }
        }

        return $months;
    }

    /**
     * Calculate AMC for each product using 70% deviation screening (same formula as WarehouseAmcCalculationService).
     * Data from issue quantity reports; months ordered newest first for screening.
     */
    private function calculateAMCWithScreening(array $months): array
    {
        $amcData = [];
        $this->info("Looking for data in months: " . implode(', ', $months));

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
            ->where(function ($query) use ($months) {
                $query->whereIn('iqr.month_year', $months)
                    ->orWhereNull('iqr.month_year');
            })
            ->groupBy('p.id', 'p.name', 'iqr.month_year')
            ->get();

        $productData = [];
        foreach ($results as $row) {
            if (!isset($productData[$row->product_id])) {
                $productData[$row->product_id] = [
                    'product_id' => $row->product_id,
                    'product_name' => $row->product_name,
                    'monthly_quantities' => [],
                ];
            }
            if ($row->month_year && (float) $row->monthly_quantity > 0) {
                $productData[$row->product_id]['monthly_quantities'][$row->month_year] = (float) $row->monthly_quantity;
            }
        }

        $amcService = app(WarehouseAmcCalculationService::class);

        foreach ($productData as $productId => $data) {
            $monthlyQuantities = $data['monthly_quantities'];
            if (empty($monthlyQuantities)) {
                $amcData[$productId] = [
                    'product_id' => $productId,
                    'product_name' => $data['product_name'],
                    'amc' => 0,
                    'months_used' => 0,
                    'total_quantity' => 0,
                    'monthly_breakdown' => [],
                ];
                $this->line("Product: {$data['product_name']} - AMC: 0 (no issue data)");
                continue;
            }

            // Build monthsData newest first (match order of $months)
            $monthsData = [];
            foreach ($months as $month) {
                if (isset($monthlyQuantities[$month])) {
                    $monthsData[] = ['month' => $month, 'consumption' => $monthlyQuantities[$month]];
                }
            }

            $result = $amcService->calculateAmcFromMonthlyData($monthsData);
            $amc = (float) $result['amc'];

            $amcData[$productId] = [
                'product_id' => $productId,
                'product_name' => $data['product_name'],
                'amc' => round($amc, 2),
                'months_used' => $result['totalMonths'],
                'total_quantity' => array_sum(array_column($monthsData, 'consumption')),
                'monthly_breakdown' => $monthlyQuantities,
            ];

            $this->line("Product: {$data['product_name']} - AMC: {$amc} (screening) - {$result['calculation']}");
        }

        // Include active products that had no issue data (so reorder_levels gets AMC = 0)
        foreach (Product::where('is_active', true)->get(['id', 'name']) as $product) {
            if (!isset($amcData[$product->id])) {
                $amcData[$product->id] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'amc' => 0,
                    'months_used' => 0,
                    'total_quantity' => 0,
                    'monthly_breakdown' => [],
                ];
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

    /**
     * Sync monthly consumption from issue quantity data into warehouse_amcs table
     * so the Warehouse AMC report and exports have data when run from schedule or "Run now".
     */
    private function syncWarehouseAmcs(array $amcData, array $months): void
    {
        $synced = 0;
        foreach ($amcData as $productId => $data) {
            $breakdown = $data['monthly_breakdown'] ?? [];
            foreach ($breakdown as $monthYear => $quantity) {
                WarehouseAmc::updateOrCreate(
                    [
                        'product_id' => $productId,
                        'month_year' => $monthYear,
                    ],
                    [
                        'quantity' => (int) round($quantity),
                    ]
                );
                $synced++;
            }
        }
        $this->info("Synced {$synced} monthly consumption records to warehouse_amcs.");
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
