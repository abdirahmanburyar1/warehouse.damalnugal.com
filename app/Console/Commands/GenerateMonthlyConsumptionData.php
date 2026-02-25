<?php

namespace App\Console\Commands;

use App\Models\EmailNotificationSetting;
use App\Models\MonthlyConsumptionReport;
use App\Models\MonthlyConsumptionItem;
use App\Models\Facility;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class GenerateMonthlyConsumptionData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consumption:generate {--facility= : Specific facility ID to generate data for} {--product= : Specific product ID to generate data for} {--force : Force regeneration even if data exists for the month}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate previous month consumption data from dispences (e.g., when run in June, generates May data)';

    public function handle()
    {
        $facilityId = $this->option('facility');
        $productId = $this->option('product');
        $force = $this->option('force');
        $today = Carbon::now();

        if (!$facilityId && !$productId && !$force) {
            $setting = EmailNotificationSetting::monthlyConsumptionSchedule();
            if (!$setting || !$setting->enabled) {
                $this->info('Monthly consumption schedule is disabled or not configured.');
                return 0;
            }
            $config = $setting->config ?? [];
            $dayOfMonth = (int) ($config['day_of_month'] ?? 1);
            $time = $this->normalizeTime($config['time'] ?? '02:00');
            $currentTime = $today->format('H:i');
            if ($today->day != $dayOfMonth || $currentTime !== $time) {
                return 0;
            }
        }

        $generatedBy = 'Artisan Command';

        // Get previous month date range
        $previousMonth = now()->subMonth();
        $monthYear = $previousMonth->format('Y-m');
        $startOfMonth = $previousMonth->copy()->startOfMonth()->toDateString();
        $endOfMonth = $previousMonth->copy()->endOfMonth()->toDateString();
        
        $this->info("Generating consumption data for {$monthYear} ({$startOfMonth} to {$endOfMonth})...");
        
        $facilities = $facilityId 
            ? Facility::where('id', $facilityId)->get() 
            : $this->getFacilities();
        
        $processedCount = 0;
        $skippedCount = 0;
        
        foreach ($facilities as $facility) {
            // Check if data already exists for this facility and month
            $existingReport = MonthlyConsumptionReport::where('facility_id', $facility->id)
                ->where('month_year', $monthYear)
                ->first();
                
            if ($existingReport && !$force) {
                $this->warn("Skipping facility {$facility->name} (ID: {$facility->id}): Data already exists for {$monthYear}");
                $skippedCount++;
                continue;
            } elseif ($existingReport) {
                // Delete existing report and its items if force option is used
                $this->warn("Removing existing data for facility {$facility->name} (ID: {$facility->id}) for {$monthYear}");
                MonthlyConsumptionItem::where('parent_id', $existingReport->id)->delete();
                $existingReport->delete();
            }
            
            // Get all dispences for this facility and month
            $dispences = DB::table('dispences')
                ->where('facility_id', $facility->id)
                ->whereBetween('dispence_date', [$startOfMonth, $endOfMonth])
                ->pluck('id');

            if ($dispences->isEmpty()) {
                $this->warn("No dispences found for facility {$facility->name} (ID: {$facility->id}) in {$monthYear}");
                $skippedCount++;
                continue;
            }

            // Aggregate dispence_items for these dispences
            $items = DB::table('dispence_items')
                ->whereIn('dispence_id', $dispences)
                ->when($productId, function ($query) use ($productId) {
                    $query->where('product_id', $productId);
                })
                ->select(
                    'product_id',
                    DB::raw('SUM(quantity) as total_quantity'),
                    DB::raw('MIN(batch_number) as batch_number'),
                    DB::raw('MIN(uom) as uom'),
                    DB::raw('MIN(expiry_date) as expiry_date'),
                    DB::raw('MIN(start_date) as first_dispense_date'),
                    DB::raw('MAX(expiry_date) as last_expiry_date')
                )
                ->groupBy('product_id')
                ->get();

            if ($items->isEmpty()) {
                $this->warn("No dispence items found for facility {$facility->name} (ID: {$facility->id}) in {$monthYear}");
                $skippedCount++;
                continue;
            }

            // Create parent report
            $report = MonthlyConsumptionReport::create([
                'facility_id' => $facility->id,
                'month_year' => $monthYear,
                'generated_by' => $generatedBy,
            ]);

            $itemCount = 0;
            foreach ($items as $item) {
                MonthlyConsumptionItem::create([
                    'parent_id' => $report->id,
                    'product_id' => $item->product_id,
                    'batch_number' => $item->batch_number,
                    'uom' => $item->uom,
                    'expiry_date' => $item->expiry_date,
                    'dispense_date' => $item->first_dispense_date,
                    'quantity' => $item->total_quantity,
                ]);
                $itemCount++;
            }
            
            $this->info("Processed facility {$facility->name} (ID: {$facility->id}): Created report with {$itemCount} items");
            $processedCount++;
        }

        $this->info("Monthly consumption data generation complete!");
        $this->info("Processed {$processedCount} facilities, skipped {$skippedCount} facilities");
    }


    /**
     * Get facilities to process.
     *
     * @param int|null $facilityId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getFacilities($facilityId = null)
    {
        $query = Facility::query()->where('is_active', true);
        
        if ($facilityId) {
            $query->where('id', $facilityId);
        }
        
        return $query->get();
    }

    /**
     * Get products to process.
     *
     * @param int|null $productId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getProducts($productId = null)
    {
        $query = Product::query()->where('is_active', true);
        
        if ($productId) {
            $query->where('id', $productId);
        }
        
        return $query->get();
    }

    /**
     * Preload all eligibility data to avoid repeated database queries.
     * Returns a map of facility_type => [product_ids]
     *
     * @return array
     */
    private function preloadEligibilityData()
    {
        $eligibilityMap = [];
        
        // Get all eligibility records
        $eligibilityRecords = DB::table('eligible_items')->get(['product_id', 'facility_type']);
        
        // Group by facility_type
        foreach ($eligibilityRecords as $record) {
            if (!isset($eligibilityMap[$record->facility_type])) {
                $eligibilityMap[$record->facility_type] = [];
            }
            $eligibilityMap[$record->facility_type][] = $record->product_id;
        }
        
        return $eligibilityMap;
    }
    
    /**
     * Insert a batch of records using a single query for better performance.
     *
     * @param array $records
     * @return void
     */
    private function insertBatch(array $records)
    {
        if (empty($records)) {
            return;
        }
        
        // Use insertOrIgnore to handle potential duplicates
        DB::table('monthly_consumptions')->insertOrIgnore($records);
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $parts = explode(':', $time);
            return sprintf('%02d:%02d', (int) $parts[0], (int) ($parts[1] ?? 0));
        }
        return '02:00';
    }
}
