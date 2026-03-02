<?php

namespace App\Http\Controllers;

use App\Models\WarehouseAmc;
use App\Models\Product;
use App\Imports\WarehouseAmcImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\WarehouseAmcExport;

class WarehouseAmcController extends Controller
{
    /**
     * Display the warehouse AMC report
     */
    public function index(Request $request)
    {

        // Get the selected year, default to current year
        $selectedYear = $request->get('year', now()->year);
        
        // Generate all months for the selected year
        $monthYears = collect();
        for ($month = 1; $month <= 12; $month++) {
            $monthYears->push($selectedYear . '-' . str_pad($month, 2, '0', STR_PAD_LEFT));
        }

        // Get years from month-years for template selection only
        $years = $monthYears->map(function($monthYear) {
            return explode('-', $monthYear)[0];
        })->unique()->sort()->values();

        // Add additional years for template selection (current year + 2 years back and 2 years forward)
        $currentYear = now()->year;
        $additionalYears = collect();
        for ($i = -2; $i <= 2; $i++) {
            $additionalYears->push($currentYear + $i);
        }
        
        // Merge and deduplicate years (for template only)
        $years = $years->merge($additionalYears)->unique()->sort()->values();

        // Get months from month-years
        $months = $monthYears->map(function($monthYear) {
            return explode('-', $monthYear)[1];
        })->unique()->sort()->values();



        // Build the pivot table query
        $query = Product::query()
            ->select([
                'products.id',
                'products.name'
            ])
            ->whereExists(function($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('warehouse_amcs')
                    ->whereColumn('warehouse_amcs.product_id', 'products.id');
            });

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            Log::info('Applying search filter', ['search' => $search]);
            $query->where('products.name', 'like', "%{$search}%");
        }



        // Apply year filter
        if ($request->filled('year')) {
            $query->whereExists(function($subQuery) use ($request) {
                $subQuery->select(DB::raw(1))
                    ->from('warehouse_amcs')
                    ->whereColumn('warehouse_amcs.product_id', 'products.id')
                    ->where('warehouse_amcs.month_year', 'like', $request->year . '-%');
            });
            Log::info('Applying year filter', ['year' => $request->year]);
        }

        // Apply sorting
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        
        $query->orderBy('products.name', $sortDirection);

        // Get all results (no pagination)
        $products = $query->get();

        // Transform data to pivot table format
        $pivotData = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'name' => $product->name,
                'months' => []
            ];

            // Get consumption data for each month
            foreach ($monthYears as $monthYear) {
                $consumption = WarehouseAmc::where('product_id', $product->id)
                    ->where('month_year', $monthYear)
                    ->value('quantity') ?? 0;
                
                $row['months'][$monthYear] = $consumption;
            }

            $row['amc'] = $this->calculateAMC($product->id, $monthYears);

            $pivotData[] = $row;
        }

        return Inertia::render('Report/WarehouseAmc', [
            'products' => $products,
            'pivotData' => $pivotData,
            'monthYears' => $monthYears,
            'filters' => $request->only(['search', 'year', 'sort', 'direction']),
            'years' => $years,
            'months' => $months,
        ]);
    }

    /**
     * Get warehouse AMC data for API
     */
    public function getData(Request $request)
    {
        $query = WarehouseAmc::query()
            ->with([
                'product:id,name,category_id,dosage_id',
                'product.category:id,name',
                'product.dosage:id,name'
            ]);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('product.category', function($q) use ($request) {
                $q->where('id', $request->category);
            });
        }

        if ($request->filled('dosage')) {
            $query->whereHas('product.dosage', function($q) use ($request) {
                $q->where('id', $request->dosage);
            });
        }

        if ($request->filled('month_year')) {
            $query->where('month_year', $request->month_year);
        }

        // Apply sorting
        $sortField = $request->get('sort', 'month_year');
        $sortDirection = $request->get('direction', 'desc');
        
        if ($sortField === 'product_name') {
            $query->whereHas('product', function($q) use ($sortDirection) {
                $q->orderBy('name', $sortDirection);
            });
        } elseif ($sortField === 'quantity') {
            $query->orderBy('quantity', $sortDirection);
        } elseif ($sortField === 'month_year') {
            $query->orderBy('month_year', $sortDirection);
        } else {
            $query->orderBy('month_year', 'desc');
        }

        $perPage = $request->get('per_page', 25);
        return $query->paginate($perPage);
    }

    /**
     * Export warehouse AMC data
     */
    public function export(Request $request)
    {
        // Get the selected year, default to current year
        $selectedYear = $request->get('year', now()->year);
        
        // Generate all months for the selected year
        $monthYears = collect();
        for ($month = 1; $month <= 12; $month++) {
            $monthYears->push($selectedYear . '-' . str_pad($month, 2, '0', STR_PAD_LEFT));
        }

        // Build the pivot table query
        $query = Product::query()
            ->select([
                'products.id',
                'products.name'
            ])
            ->whereExists(function($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('warehouse_amcs')
                    ->whereColumn('warehouse_amcs.product_id', 'products.id');
            });

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('products.name', 'like', "%{$search}%");
        }



        // Apply year filter
        if ($request->filled('year')) {
            $query->whereExists(function($subQuery) use ($request) {
                $subQuery->select(DB::raw(1))
                    ->from('warehouse_amcs')
                    ->whereColumn('warehouse_amcs.product_id', 'products.id')
                    ->where('warehouse_amcs.month_year', 'like', $request->year . '-%');
            });
        }

        $products = $query->orderBy('products.name')->get();

        // Transform data to pivot table format
        $pivotData = [];
        foreach ($products as $product) {
            $row = [
                'name' => $product->name,
            ];

            // Add consumption data for each month
            foreach ($monthYears as $monthYear) {
                $consumption = WarehouseAmc::where('product_id', $product->id)
                    ->where('month_year', $monthYear)
                    ->value('quantity') ?? 0;
                
                $row[$monthYear] = $consumption;
            }

            $row['AMC'] = $this->calculateAMC($product->id, $monthYears);

            $pivotData[] = $row;
        }

        $filename = 'warehouse_amc_report_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new WarehouseAmcExport($pivotData, $monthYears), $filename);
    }

    /**
     * Import warehouse AMC data from Excel
     */
    public function import(Request $request)
    {
        try {
            Log::info('=== WAREHOUSE AMC IMPORT STARTED ===');
            
            if (!$request->hasFile('file')) {
                Log::warning('No file uploaded in request');
                return response()->json([
                    'success' => false,
                    'message' => 'No file was uploaded'
                ], 422);
            }

            $file = $request->file('file');
            Log::info('File received', [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension()
            ]);

            // Validate file type
            $extension = $file->getClientOriginalExtension();
            if (!$file->isValid() || !in_array($extension, ['xlsx', 'xls', 'csv'])) {
                Log::warning('Invalid file type', ['extension' => $extension]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file type. Please upload an Excel file (.xlsx, .xls) or CSV file (.csv)'
                ], 422);
            }

            $importId = (string) Str::uuid();
            Log::info('Generated import ID', ['import_id' => $importId]);

            Log::info('Starting warehouse AMC import with Maatwebsite Excel', [
                'import_id' => $importId,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'extension' => $extension
            ]);

            // Initialize cache progress for tracking
            Cache::put("warehouse_amc_import_{$importId}", [
                'status' => 'processing',
                'progress' => 0,
                'message' => 'Import started'
            ], 3600); // 1 hour expiry
            Log::info('Cache initialized for import tracking');

            // Determine import method based on file size
            $fileSize = $file->getSize();
            $largeFileThreshold = 5 * 1024 * 1024; // 5MB
            Log::info('File size analysis', [
                'file_size' => $fileSize,
                'large_file_threshold' => $largeFileThreshold,
                'is_large_file' => $fileSize > $largeFileThreshold
            ]);

            if ($fileSize > $largeFileThreshold) {
                Log::info('Large file detected, attempting queued import');
                // Use queued import for large files
                try {
                    // Store the file permanently for queued processing
                    $storedPath = $file->store('warehouse-amc-imports', 'local');
                    Log::info('File stored for queued import', ['stored_path' => $storedPath]);
                    
                    Excel::queueImport(new WarehouseAmcImport($importId, $storedPath), $storedPath)->onQueue('imports');
                    Log::info('Queued import successful');
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Large file detected. Import has been queued and will process in the background.',
                        'import_id' => $importId,
                        'queued' => true
                    ]);
                    
                } catch (\Exception $queueError) {
                    Log::warning('Queue import failed, falling back to synchronous', [
                        'error' => $queueError->getMessage(),
                        'trace' => $queueError->getTraceAsString()
                    ]);
                    // Fall through to synchronous import
                }
            }

            Log::info('Using synchronous import');
            // Use synchronous import for smaller files or if queue failed
            $import = new WarehouseAmcImport($importId);
            Log::info('WarehouseAmcImport instance created', ['import_id' => $importId]);
            
            Log::info('Starting Excel::import()');
            Excel::import($import, $file);
            Log::info('Excel::import() completed successfully');
            
            // Get import results
            $results = $import->getResults();
            Log::info('Import results retrieved', $results);
            
            $message = "Import completed successfully. ";
            if ($results['imported'] > 0) {
                $message .= "Created: {$results['imported']} new AMC records. ";
            }
            if ($results['updated'] > 0) {
                $message .= "Updated: {$results['updated']} existing AMC records. ";
            }
            if ($results['skipped'] > 0) {
                $message .= "Skipped: {$results['skipped']} rows. ";
            }

            Log::info('Final message prepared', ['message' => trim($message)]);

            // Update cache with completion status
            Cache::put("warehouse_amc_import_{$importId}", [
                'status' => 'completed',
                'progress' => 100,
                'message' => trim($message),
                'results' => $results
            ], 3600);
            Log::info('Cache updated with completion status');

            Log::info('=== WAREHOUSE AMC IMPORT COMPLETED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => trim($message),
                'import_id' => $importId,
                'results' => $results,
                'queued' => false
            ]);

        } catch (\Throwable $e) {
            Log::error('=== WAREHOUSE AMC IMPORT FAILED ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update cache with error status
            if (isset($importId)) {
                Cache::put("warehouse_amc_import_{$importId}", [
                    'status' => 'failed',
                    'progress' => 0,
                    'message' => 'Import failed: ' . $e->getMessage()
                ], 3600);
                Log::info('Cache updated with error status');
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check import status
     */
    public function checkImportStatus($importId)
    {
        try {
            $cacheKey = "warehouse_amc_import_{$importId}";
            $status = Cache::get($cacheKey);
            
            if (!$status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import not found or expired',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $status,
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Import status check failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check import status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download template for warehouse AMC import
     */
    public function downloadTemplate(Request $request)
    {
        try {
            Log::info('=== TEMPLATE DOWNLOAD STARTED ===');
            
            // Get the selected year from request, default to current year
            $selectedYear = $request->get('year', now()->year);
            Log::info('Template year selected', ['year' => $selectedYear]);
            
            // Always generate all 12 months for the selected year
            $monthYears = collect();
            for ($month = 1; $month <= 12; $month++) {
                $monthYears->push($selectedYear . '-' . str_pad($month, 2, '0', STR_PAD_LEFT));
            }
            
            Log::info('Generated months for year', [
                'year' => $selectedYear,
                'month_count' => $monthYears->count(),
                'months' => $monthYears->toArray()
            ]);

            // Get ALL products for template
            Log::info('Fetching all products for template');
            $allProducts = Product::select([
                'products.id',
                'products.name'
            ])
                ->orderBy('products.name')
                ->get();
            
            Log::info('Products fetched for template', [
                'product_count' => $allProducts->count(),
                'sample_products' => $allProducts->take(3)->map(function($p) {
                    return ['id' => $p->id, 'name' => $p->name];
                })->toArray()
            ]);

            // Create template data with ALL products
            $templateData = [];
            foreach ($allProducts as $product) {
                $row = [
                    'name' => $product->name,
                ];

                // Add sample quantities for each month (empty for template - won't overwrite existing data)
                foreach ($monthYears as $monthYear) {
                    $row[$monthYear] = ''; // Empty cell - won't overwrite existing data
                }



                $templateData[] = $row;
            }
            
            Log::info('Template data prepared', [
                'template_rows' => count($templateData),
                'template_columns' => count($templateData[0] ?? []),
                'sample_row' => $templateData[0] ?? 'No data'
            ]);

            $filename = 'warehouse_amc_import_template_' . $selectedYear . '_' . now()->format('Y-m-d') . '.xlsx';
            Log::info('Template filename prepared', ['filename' => $filename]);
            
            Log::info('=== TEMPLATE DOWNLOAD COMPLETED SUCCESSFULLY ===');
            return Excel::download(new WarehouseAmcExport($templateData, $monthYears, true), $filename);

        } catch (\Exception $e) {
            Log::error('=== TEMPLATE DOWNLOAD FAILED ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Template download failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate AMC using exact 70% deviation screening formula
     */
    private function calculateAMC($productId, $monthYears)
    {
        try {
            // Get all consumption values for the product with month_year for ordering
            $consumptionsWithMonth = WarehouseAmc::where('product_id', $productId)
                ->whereIn('month_year', $monthYears)
                ->where('quantity', '>', 0) // Only consider positive consumption values
                ->orderBy('month_year', 'desc') // Most recent first (bottom to top)
                ->get(['month_year', 'quantity']);

            // If we have less than 3 values, return 0
            if ($consumptionsWithMonth->count() < 3) {
                return 0;
            }

            // Extract quantities and months
            $quantities = $consumptionsWithMonth->pluck('quantity')->values();
            $months = $consumptionsWithMonth->pluck('month_year')->values();
            
            Log::info("AMC calculation for product {$productId}", [
                'quantities' => $quantities->toArray(),
                'months' => $months->toArray()
            ]);

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
                Log::info("AMC calculation attempt {$attempt}", [
                    'selected_months' => $selectedMonths,
                    'passed_months' => $passedMonths,
                    'failed_months' => $failedMonths
                ]);
                
                // Calculate average of selected months
                $average = collect($selectedMonths)->avg('quantity');
                
                Log::info("Calculated average", ['average' => $average]);
                
                // Check each month's deviation
                $allPassed = true;
                $newPassedMonths = [];
                $newFailedMonths = [];
                
                foreach ($selectedMonths as $monthData) {
                    $quantity = $monthData['quantity'];
                    $deviation = abs($quantity - $average) / $average * 100;
                    
                    Log::info("Checking month {$monthData['month']}", [
                        'quantity' => $quantity,
                        'deviation_percentage' => round($deviation, 2)
                    ]);
                    
                    if ($deviation <= 70) {
                        // Month passed screening
                        $newPassedMonths[] = $monthData;
                        Log::info("Month {$monthData['month']} PASSED screening");
                    } else {
                        // Month failed screening
                        $newFailedMonths[] = $monthData;
                        $allPassed = false;
                        Log::info("Month {$monthData['month']} FAILED screening");
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
                    Log::info("All months passed screening!", [
                        'final_selected_months' => $selectedMonths,
                        'final_passed_months' => $passedMonths
                    ]);
                    break;
                }
                
                // If we have 3 or more passed months, use them
                if (count($passedMonths) >= 3) {
                    $selectedMonths = array_slice($passedMonths, 0, 3);
                    Log::info("Using 3 passed months", ['selected_months' => $selectedMonths]);
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
                
                Log::info("Reselected months for next attempt", [
                    'new_selection' => $selectedMonths
                ]);
                
                $attempt++;
            }
            
            // Calculate final AMC
            if (count($selectedMonths) >= 3) {
                $amc = collect($selectedMonths)->avg('quantity');
                $result = round($amc, 2);
                
                Log::info("AMC calculation completed", [
                    'product_id' => $productId,
                    'final_selected_months' => $selectedMonths,
                    'amc' => $result
                ]);
                
                return $result;
            } else {
                Log::warning("Could not find 3 suitable months for AMC calculation", [
                    'product_id' => $productId,
                    'selected_months_count' => count($selectedMonths)
                ]);
                return 0;
            }

        } catch (\Exception $e) {
            Log::error('Error calculating AMC for product ' . $productId, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }
}
