<?php

namespace App\Console\Commands;

use App\Models\EmailNotificationSetting;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\AmcCalculationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\MonthlyConsumptionItem;
use App\Models\Facility;
use App\Models\Order;
use App\Mail\QuarterlyOrderGenerated;

class GenerateQuarterlyOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:generate-quarterly {quarter? : Target quarter (1-4)} {year? : Target year} {--test : Test mode - bypass date validation} {--force : Force generation even if orders already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate quarterly orders for all active facilities using advanced AMC screening logic. Optionally specify quarter (1-4) and year.';

    public function handle()
    {
        $today = now();
        $targetQuarter = $this->argument('quarter') ?? $this->getCurrentQuarter();
        $year = $this->argument('year') ?? $today->year;

        // When run from scheduler (no args, not --test): only run if schedule enabled and today is quarter start at configured time
        if (!$this->argument('quarter') && !$this->argument('year') && !$this->option('test')) {
            $setting = EmailNotificationSetting::ordersQuarterlySchedule();
            if (!$setting || !$setting->enabled) {
                $this->info('Quarterly orders schedule is disabled or not configured.');
                return 0;
            }
            $quarterStartDates = [
                1 => ['month' => 12, 'day' => 1],
                2 => ['month' => 3, 'day' => 1],
                3 => ['month' => 6, 'day' => 1],
                4 => ['month' => 9, 'day' => 1],
            ];
            $isQuarterStartDate = false;
            foreach ($quarterStartDates as $quarter => $dates) {
                if ($today->month == $dates['month'] && $today->day == $dates['day']) {
                    $isQuarterStartDate = true;
                    $targetQuarter = $quarter;
                    break;
                }
            }
            if (!$isQuarterStartDate) {
                return 0;
            }
            $config = $setting->config ?? [];
            $time = $this->normalizeTime($config['time'] ?? '01:00');
            if ($today->format('H:i') !== $time) {
                return 0;
            }
        }

        $this->info('Starting quarterly order generation with advanced AMC screening logic...');

        try {
            // Handle year rollover for Q1 (Dec-Feb) when we're in Dec
            if ($targetQuarter == 1 && $today->month == 12 && !$this->argument('quarter')) {
                $year = $today->year + 1;
            }

            // Check if today is the first day of a quarter (only if no arguments provided and not in test mode)
            if (!$this->argument('quarter') && !$this->argument('year') && !$this->option('test')) {
                $this->info("✅ Confirmed: Today is the start of Q{$targetQuarter} {$year}");
            } elseif ($this->option('test')) {
                $this->warn("🧪 TEST MODE: Bypassing date validation - generating Q{$targetQuarter} {$year}");
            } else {
                $this->warn("⚠️ Manual override: Quarter {$targetQuarter} and year {$year} specified");
            }

            $this->info("Generating orders for Q{$targetQuarter} {$year}");

            // Get all facility types that have eligible items
            $facilityTypes = DB::table('eligible_items')
                ->distinct()
                ->pluck('facility_type')
                ->toArray();

            if (empty($facilityTypes)) {
                $this->error("No facility types found with eligible items");
                return 1;
            }

            $this->info("Found facility types with eligible items: " . implode(', ', $facilityTypes));
            $this->newLine();

            $totalProcessed = 0;
            $totalFailed = 0;
            $totalOrders = 0;
            $totalEmailsSent = 0;

            // Process each facility type and its facilities
            foreach ($facilityTypes as $facilityType) {
                $this->info("🏭 Processing facility type: {$facilityType}");
                
                // Get eligible items for this facility type
                $eligibleItems = DB::table('eligible_items')
                    ->where('facility_type', $facilityType)
                    ->join('products', 'eligible_items.product_id', '=', 'products.id')
                    ->select('eligible_items.id', 'eligible_items.product_id', 'products.name as product_name', 'eligible_items.facility_type')
                    ->orderBy('eligible_items.id')
                    ->get();

                $this->info("   📦 Found {$eligibleItems->count()} eligible items for {$facilityType}");

                // Get all active facilities of this type
                $facilities = DB::table('facilities')
                    ->where('facility_type', $facilityType)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get();

                if ($facilities->isEmpty()) {
                    $this->warn("   ⚠️ No active facilities found for type: {$facilityType}");
                    continue;
                }

                $this->info("   🏥 Found {$facilities->count()} active facilities of type: {$facilityType}");

                // Check for existing quarterly orders for this quarter/year
                $existingOrdersCount = $this->checkExistingQuarterlyOrders($facilities, $targetQuarter, $year);
                if ($existingOrdersCount > 0) {
                    $this->warn("   ⚠️ Found {$existingOrdersCount} existing quarterly orders for Q{$targetQuarter} {$year} in {$facilityType} facilities");
                    
                    if (!$this->option('test') && !$this->option('force')) {
                        $this->error("   ❌ Quarterly orders for Q{$targetQuarter} {$year} already exist for {$facilityType} facilities");
                        $this->error("   💡 Use --test or --force mode to override this check:");
                        $this->error("      • --test: Test mode bypass");
                        $this->error("      • --force: Force regeneration (may create duplicates)");
                        continue; // Skip this facility type
                    } else {
                        if ($this->option('test')) {
                            $this->warn("   🧪 TEST MODE: Proceeding despite existing orders");
                        }
                        if ($this->option('force')) {
                            $this->warn("   🔧 FORCE MODE: Proceeding despite existing orders (may create duplicates)");
                        }
                    }
                }

                $this->newLine();

                // Process each facility for this facility type
                foreach ($facilities as $index => $facility) {
                    $facilityName = isset($facility->name) ? $facility->name : 'Unknown';
                    $facilityNumber = $index + 1;
                    $this->info("      🏥 Processing facility {$facilityNumber}/{$facilities->count()}: {$facility->id} ({$facilityName})");
                    
                    try {
                        // Create order for this facility
                        $orderId = $this->createOrder($facility, $targetQuarter, $year);
                        if (!$orderId) {
                            $this->error("         ❌ Failed to create order for facility {$facility->id}");
                            $totalFailed++;
                            continue;
                        }

                        $this->info("         📋 Created order ID: {$orderId}");

                        // Process the eligible items for this specific facility
                        $result = $this->processFacilityItems($orderId, $facility, $eligibleItems);
                        
                        if ($result['success']) {
                            $totalProcessed++;
                            $totalOrders++;
                            $this->info("         ✅ Completed facility {$facility->id} - Processed: {$result['processed']}, Skipped: {$result['skipped']}, Errors: {$result['errors']}");
                            
                            // Send email notification
                            if ($this->sendOrderNotification($orderId, $facility, $result)) {
                                $totalEmailsSent++;
                            }
                        } else {
                            $totalFailed++;
                            $this->error("         ❌ Failed to process facility {$facility->id}");
                        }
                        
                    } catch (\Exception $e) {
                        $this->error("         ❌ Error processing facility {$facility->id}: " . $e->getMessage());
                        
                        $totalFailed++;
                    }
                    
                    // Small delay between facilities
                    usleep(50000); // 50ms delay
                }
                
                $this->newLine();
                $this->info("   ✅ Completed facility type: {$facilityType}");
                $this->newLine();
            }

            // Final summary
            $this->newLine();
            $this->info("🎯 Final Summary:");
            $this->info("   📊 Total facility types processed: " . count($facilityTypes));
            $this->info("   📋 Total orders created: {$totalOrders}");
            $this->info("   ✅ Successfully processed facilities: {$totalProcessed}");
            $this->info("   📧 Email notifications sent: {$totalEmailsSent}");
            if ($totalFailed > 0) {
                $this->warn("   ❌ Failed to process facilities: {$totalFailed}");
            }
            


            $this->info("✅ Quarterly order generation completed successfully!");
            if ($totalEmailsSent > 0) {
                $this->info("📧 Email notifications have been sent to facility managers with order details and links.");
            }
            return 0;

        } catch (\Exception $e) {
            $this->error("Fatal error: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Create the order record
     */
    private function createOrder($facility, $targetQuarter, $year)
    {
        // Generate sequential order number for the quarter
        $orderNumber = $this->generateSequentialOrderNumber($targetQuarter, $year);
        
        $maxRetries = 3;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                DB::beginTransaction();
                
                // Map quarter to correct start month
                $quarterStartMonths = [
                    1 => 12,  // Q1: December
                    2 => 3,   // Q2: March
                    3 => 6,   // Q3: June
                    4 => 9,   // Q4: September
                ];
                
                $startMonth = $quarterStartMonths[$targetQuarter];
                $orderYear = $year;
                
                // Handle year adjustment for Q1 starting in December
                if ($targetQuarter == 1 && $startMonth == 12) {
                    // Q1 starts in December of the previous year
                    $orderYear = $year - 1;
                }
                
                $orderId = DB::table('orders')->insertGetId([
                    'facility_id' => $facility->id,
                    'user_id' => 1, // System generated order
                    'order_number' => $orderNumber,
                    'order_type' => 'Quarterly Q-'.$targetQuarter,
                    'status' => 'pending',
                    'order_date' => Carbon::create($orderYear, $startMonth, 1),
                    'expected_date' => Carbon::create($orderYear, $startMonth, 1)->addDays(7),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                DB::commit();
                $this->info("✅ Created order: {$orderNumber} (ID: {$orderId})");
                return $orderId;
                
            } catch (\Exception $e) {
                DB::rollBack();
                $attempt++;
                
                if ($attempt >= $maxRetries) {
                    $this->error("❌ Failed to create order after {$maxRetries} attempts: " . $e->getMessage());
                    return null;
                }
                
                $this->warn("⚠️ Retrying order creation (attempt {$attempt})...");
                sleep(2); // Wait 2 seconds before retry
            }
        }
        
        return null;
    }

    /**
     * Generate sequential order number for the quarter
     */
    private function generateSequentialOrderNumber($targetQuarter, $year)
    {
        $quarterPrefix = "Q{$targetQuarter}-{$year}";
        
        // Map quarter to correct start month
        $quarterStartMonths = [
            1 => 12,  // Q1: December
            2 => 3,   // Q2: March  
            3 => 6,   // Q3: June
            4 => 9,   // Q4: September
        ];
        
        $startMonth = $quarterStartMonths[$targetQuarter];
        $searchYear = $year;
        
        // Handle year adjustment for Q1 starting in December
        if ($targetQuarter == 1 && $startMonth == 12) {
            $searchYear = $year - 1;
        }
        
        // Get the highest existing order number for this quarter
        $lastOrderNumber = DB::table('orders')
            ->where('order_type', 'LIKE', '%Q-' . $targetQuarter)
            ->where('order_date', '>=', Carbon::create($searchYear, $startMonth, 1))
            ->where('order_date', '<', Carbon::create($searchYear, $startMonth, 1)->addMonths(3))
            ->where('order_number', 'LIKE', $quarterPrefix . '-%')
            ->orderBy('order_number', 'desc')
            ->value('order_number');

        if ($lastOrderNumber) {
            // Extract the sequence number from the last order
            $parts = explode('-', $lastOrderNumber);
            $lastSequence = intval(end($parts));
            $nextSequence = $lastSequence + 1;
        } else {
            // First order for this quarter
            $nextSequence = 1;
        }

        // Format as Q3-2025-0001
        return $quarterPrefix . '-' . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Process a single order item with micro-transaction (pattern from successful imports)
     * 
     * AMC Screening Process (from facilities OrderController):
     * 1. Exclude current month from consumption data
     * 2. Screen months to find 3 that have ≤70% deviation from their average
     * 3. Calculate AMC (Average Monthly Consumption) from selected 3 months
     * 4. Project consumption for remaining quarter days: (AMC / 30) * daysRemaining
     * 5. Calculate required quantity: ceil(projected consumption - SOH - QOO)
     */
    private function processOrderItem($orderId, $facilityId, $item, $months, &$deletedBatches)
    {
        $maxRetries = 2;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                DB::beginTransaction();
                
                // Use the AMC calculation service for consistent calculation
                $amcService = new AmcCalculationService();
                $amcResult = $amcService->calculateAmc($facilityId, $item->product_id);
                
                $amc = $amcResult['amc'];
                $selectedMonths = $amcResult['selectedMonths'];
                $totalMonths = $amcResult['totalMonths'];

                // Get SOH
                $soh = DB::table('facility_inventory_items')
                    ->join('facility_inventories', 'facility_inventory_items.facility_inventory_id', '=', 'facility_inventories.id')
                    ->where('facility_inventories.facility_id', $facilityId)
                    ->where('facility_inventory_items.product_id', $item->product_id)
                    ->where('facility_inventory_items.expiry_date', '>=', now()->toDateString())
                    ->sum('facility_inventory_items.quantity');

                // Calculate days remaining in quarter cycle using quarter-based logic
                $now = Carbon::now();
                $quarter = $this->getCurrentQuarter();
                $quarterStartDates = [
                    1 => '01-12',  // Q1: Dec 1 - Feb 28/29 (90 days)
                    2 => '01-03',  // Q2: Mar 1 - May 31 (90 days)
                    3 => '01-06',  // Q3: Jun 1 - Aug 31 (90 days)
                    4 => '01-09'   // Q4: Sep 1 - Nov 30 (90 days)
                ];
                
                $quarterStartDateParts = explode('-', $quarterStartDates[$quarter]);
                $quarterStart = Carbon::createFromDate($now->year, $quarterStartDateParts[1], $quarterStartDateParts[0])->startOfDay();
                
                // Handle year rollover for quarters that span across years
                if ($quarter == 1 && $now->month <= 2) {
                    // For Q1 (Dec-Feb), if we're in Jan/Feb, the quarter started in December of previous year
                    $quarterStart = Carbon::createFromDate($now->year - 1, 12, 1)->startOfDay();
                }
                
                $daysSince = $quarterStart->diffInDays($now->startOfDay());
                // Ensure days remaining is between 1 and 90 (quarter is exactly 90 days)
                $daysRemaining = max(1, min(90, 90 - $daysSince));

                // Quantity on Order (QOO) default to 0
                $qoo = 0;

                // Calculate projected consumption:
                // AMC is average monthly, so convert to daily by dividing by ~30 days
                $projectedConsumption = ($amc / 30) * $daysRemaining;

                // Calculate required quantity = projected consumption - SOH - QOO
                $neededQuantity = ceil($projectedConsumption - $soh - $qoo);
                $neededQuantity = max(0, $neededQuantity);

                // If no AMC and no SOH and quantity zero, assign default order quantity (first time order)
                if ($amc == 0 && $soh == 0 && $neededQuantity == 0) {
                    $neededQuantity = (int) $daysRemaining; // default value for first order, adjust as needed
                }

                // Log AMC screening results for transparency
                Log::info("AMC Screening Process", [
                    'facility_id' => $facilityId,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name ?? 'Unknown',
                    'total_months_available' => $totalMonths,
                    'screening_process' => [
                        'step_1' => 'Exclude current month if present',
                        'step_2' => 'Find 3 months with ≤70% deviation from their average',
                        'step_3' => 'Calculate AMC from selected months',
                        'step_4' => 'Project consumption for remaining quarter days'
                    ],
                    'screening_results' => [
                        'months_selected_for_amc' => count($selectedMonths),
                        'calculated_amc' => round($amc, 2),
                        'calculation_method' => $amcResult['calculation'],
                        'selected_months_data' => $selectedMonths
                    ],
                    'quantity_calculation' => [
                        'soh' => $soh,
                        'days_remaining_in_quarter' => $daysRemaining,
                        'daily_consumption_rate' => round($amc / 30, 4),
                        'projected_consumption' => round($projectedConsumption, 2),
                        'final_needed_quantity' => $neededQuantity
                    ]
                ]);

                // Skip if no quantity needed
                if ($neededQuantity == 0) {
                    DB::commit();
                    return 'skipped';
                }

                // Create order item
                $orderItemId = DB::table('order_items')->insertGetId([
                    'order_id' => $orderId,
                    'product_id' => $item->product_id,
                    'quantity' => $neededQuantity,
                    'quantity_on_order' => 0,
                    'soh' => $soh,
                    'amc' => round($amc, 2),
                    'quantity_to_release' => 0,
                    'no_of_days' => $daysRemaining,
                    'days' => $daysRemaining,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Process inventory allocation if needed
                if ($neededQuantity > 0) {
                    $inventories = DB::table('inventory_items')
                        ->where('product_id', $item->product_id)
                        ->where('quantity', '>', 0)
                        ->where('expiry_date', '>=', now()->toDateString())
                        ->select('id', 'inventory_id', 'product_id', 'warehouse_id', 'location', 'batch_number', 'expiry_date', 'quantity', 'unit_cost', 'uom', 'source')
                        ->orderBy('expiry_date')
                        ->get();

                    $totalAvailable = $inventories->sum('quantity');
                    $totalToAllocate = min($neededQuantity, $totalAvailable);
                    $batchCount = $inventories->count();

                    if ($totalToAllocate > 0) {
                        // Log multi-batch allocation info
                        if ($batchCount > 1) {
                            Log::info("Multi-batch allocation", [
                                'product_id' => $item->product_id,
                                'product_name' => $item->product_name ?? 'Unknown',
                                'needed_quantity' => $neededQuantity,
                                'available_batches' => $batchCount,
                                'total_available' => $totalAvailable,
                                'will_allocate' => $totalToAllocate,
                                'sources' => $inventories->pluck('source', 'batch_number')->toArray()
                            ]);
                        }

                        // Update order item with allocation
                        DB::table('order_items')
                            ->where('id', $orderItemId)
                            ->update(['quantity_to_release' => $totalToAllocate]);

                        $remainingToAllocate = $totalToAllocate;
                        $allocatedBatches = [];
                        
                        foreach ($inventories as $inventory) {
                            if ($remainingToAllocate <= 0) break;

                            $batchAllocation = min($inventory->quantity, $remainingToAllocate);

                            // Track allocated batches for logging
                            $allocatedBatches[] = [
                                'batch_number' => $inventory->batch_number,
                                'allocated_quantity' => $batchAllocation,
                                'expiry_date' => $inventory->expiry_date,
                                'source' => $inventory->source ?? 'warehouse'
                            ];

                            // Create allocation
                            DB::table('inventory_allocations')->insert([
                                'order_item_id' => $orderItemId,
                                'product_id' => $item->product_id,
                                'warehouse_id' => $inventory->warehouse_id,
                                'location' => $inventory->location,
                                'batch_number' => $inventory->batch_number,
                                'expiry_date' => $inventory->expiry_date,
                                'allocated_quantity' => $batchAllocation,
                                'allocation_type' => 'quarterly',
                                'unit_cost' => $inventory->unit_cost,
                                'total_cost' => $inventory->unit_cost * $batchAllocation,
                                'uom' => $inventory->uom,
                                'source' => $inventory->source ?? 'warehouse', // Capture source from inventory_items
                                'notes' => "Quarterly allocation from batch {$inventory->batch_number}",
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            // Update inventory item (specific batch)
                            $newQuantity = $inventory->quantity - $batchAllocation;
                            
                            if ($newQuantity <= 0) {
                                // Delete the inventory item if quantity becomes zero or negative
                                DB::table('inventory_items')->where('id', $inventory->id)->delete();
                                $deletedBatches++;
                                $this->info("         🗑️ Deleted empty batch: {$inventory->batch_number} (Product ID: {$item->product_id})");
                            } else {
                                // Update inventory item with remaining quantity
                            DB::table('inventory_items')
                                ->where('id', $inventory->id)
                                ->update([
                                        'quantity' => $newQuantity,
                                        'total_cost' => DB::raw("unit_cost * {$newQuantity}")
                                ]);
                            }

                            // Update parent inventory (total quantity for product)
                            DB::table('inventories')
                                ->where('id', $inventory->inventory_id)
                                ->update([
                                    'quantity' => DB::raw("quantity - {$batchAllocation}")
                                ]);

                            $remainingToAllocate -= $batchAllocation;
                        }

                    }
                }

                DB::commit();
                return 'success';
                
            } catch (\Exception $e) {
                DB::rollBack();
                $attempt++;
                
                if ($attempt >= $maxRetries) {
                    return 'error';
                }
                
                // Brief wait before retry
                usleep(50000); // 50ms
            }
        }
        
        return 'error';
    }

    /**
     * Process the eligible items for a specific facility
     */
    private function processFacilityItems($orderId, $facility, $eligibleItems)
    {
        $processed = 0;
        $skipped = 0;
        $errors = 0;
        $deletedBatches = 0;

        $this->info("   📦 Found {$eligibleItems->count()} eligible items for facility {$facility->id}");
        $bar = $this->output->createProgressBar($eligibleItems->count());
        $bar->start();

                 // Pre-calculate months for AMC to avoid repeated calculations
         $months = [];
         $currentDate = now()->subMonth();
         for ($i = 0; $i < 3; $i++) {
             $months[] = $currentDate->format('Y-m');
             $currentDate->subMonth();
         }

         foreach ($eligibleItems as $item) {
             $result = $this->processOrderItem($orderId, $facility->id, $item, $months, $deletedBatches);
            
            switch ($result) {
                case 'success':
                    $processed++;
                    break;
                case 'skipped':
                    $skipped++;
                    break;
                case 'error':
                default:
                    $errors++;
                    break;
            }
            
            $bar->advance();
            
            // Reduced delay for better performance with 76 facilities
            usleep(5000); // 5ms delay
        }
        
        $bar->finish();
        $this->newLine();
        
        $this->info("   📊 Processing Summary for Facility {$facility->id}:");
        $this->info("      ✅ Successfully processed: {$processed} items");
        if ($skipped > 0) {
            $this->info("      ⏭️ Skipped (no quantity needed): {$skipped} items");
        }
        if ($errors > 0) {
            $this->warn("      ⚠️ Failed to process: {$errors} items");
        }
        if ($deletedBatches > 0) {
            $this->info("      🗑️ Cleaned up empty batches: {$deletedBatches} batches");
        }
        
        Log::info("Facility quarterly order processing completed", [
            'facility_id' => $facility->id,
            'facility_type' => $facility->facility_type,
            'order_id' => $orderId,
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
            'deleted_batches' => $deletedBatches,
            'total_eligible' => $eligibleItems->count()
        ]);

        return [
            'success' => $processed > 0,
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }

    /**
     * Check for existing quarterly orders for the given facilities, quarter, and year
     */
    private function checkExistingQuarterlyOrders($facilities, $targetQuarter, $year)
    {
        $facilityIds = $facilities->pluck('id')->toArray();
        
        // Map quarter to correct start month for date range checking
        $quarterStartMonths = [
            1 => 12,  // Q1: December
            2 => 3,   // Q2: March
            3 => 6,   // Q3: June
            4 => 9,   // Q4: September
        ];
        
        $startMonth = $quarterStartMonths[$targetQuarter];
        $searchYear = $year;
        
        // Handle year adjustment for Q1 starting in December
        if ($targetQuarter == 1 && $startMonth == 12) {
            $searchYear = $year - 1;
        }
        
        $startDate = Carbon::create($searchYear, $startMonth, 1);
        $endDate = $startDate->copy()->addMonths(3);
        
        $existingOrders = DB::table('orders')
            ->whereIn('facility_id', $facilityIds)
            ->where('order_type', 'LIKE', '%Q-' . $targetQuarter)
            ->where('order_date', '>=', $startDate)
            ->where('order_date', '<', $endDate)
            ->count();
            
        return $existingOrders;
    }

    /**
     * Determine the current quarter based on the current date
     * 
     * @return int
     */
    private function getCurrentQuarter()
    {
        $now = Carbon::now();
        $month = $now->month;
        
        // Q1: Dec (12) - Feb (2)
        if ($month == 12 || $month <= 2) {
            return 1;
        }
        // Q2: Mar (3) - May (5)
        elseif ($month >= 3 && $month <= 5) {
            return 2;
        }
        // Q3: Jun (6) - Aug (8)
        elseif ($month >= 6 && $month <= 8) {
            return 3;
        }
        // Q4: Sep (9) - Nov (11)
        elseif ($month >= 9 && $month <= 11) {
            return 4;
        }
        
        // Fallback (should not reach here)
        return 1;
    }

    /**
     * Send email notification for quarterly order generation
     */
    private function sendOrderNotification($orderId, $facility, $result)
    {
        try {
            // Get the order with full details
            $order = Order::find($orderId);
            if (!$order) {
                $this->warn("         ⚠️ Could not find order {$orderId} for email notification");
                return false;
            }

            // Get facility with relationships
            $facilityModel = Facility::with('handledby')->find($facility->id);
            if (!$facilityModel) {
                $this->warn("         ⚠️ Could not find facility {$facility->id} for email notification");
                return false;
            }

            // Prepare order summary for email
            $orderSummary = [
                'total_items' => $result['processed'] + $result['skipped'] + $result['errors'],
                'processed' => $result['processed'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors']
            ];

            // Determine recipient email
            $recipientEmail = null;
            $recipientName = 'Facility Manager';

            // Priority 1: Check if facility has handled_by user with email
            if ($facilityModel->handledby && $facilityModel->handledby->email) {
                $recipientEmail = $facilityModel->handledby->email;
                $recipientName = $facilityModel->handledby->name ?? 'Facility Manager';
                $this->info("         📧 Sending to handled_by user: {$recipientName} ({$recipientEmail})");
            }
            // Priority 2: Use facility's direct email
            elseif ($facilityModel->email) {
                $recipientEmail = $facilityModel->email;
                $this->info("         📧 Sending to facility email: {$recipientEmail}");
            }
            else {
                $this->warn("         ⚠️ No email found for facility {$facility->id} - skipping notification");
                return false;
            }

            // Send the email
            Mail::to($recipientEmail, $recipientName)->send(
                new QuarterlyOrderGenerated($order, $facilityModel, $orderSummary)
            );

            $this->info("         ✅ Email notification sent to {$recipientEmail}");

            Log::info("Quarterly order email notification sent", [
                'order_id' => $orderId,
                'facility_id' => $facility->id,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'order_summary' => $orderSummary
            ]);

            return true;

        } catch (\Exception $e) {
            $this->warn("         ⚠️ Failed to send email notification: " . $e->getMessage());
            Log::error("Failed to send quarterly order email notification", [
                'order_id' => $orderId,
                'facility_id' => $facility->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $parts = explode(':', $time);
            return sprintf('%02d:%02d', (int) $parts[0], (int) ($parts[1] ?? 0));
        }
        return '01:00';
    }
}
