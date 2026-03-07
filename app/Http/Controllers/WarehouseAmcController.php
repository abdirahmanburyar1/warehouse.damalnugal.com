<?php

namespace App\Http\Controllers;

use App\Models\WarehouseAmc;
use App\Models\Product;
use App\Imports\WarehouseAmcImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\WarehouseAmcExport;
use App\Services\WarehouseAmcCalculationService;

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
        // NOTE: Export should include ALL products, not only those that already have AMC records.
        $query = Product::query()
            ->select([
                'products.id',
                'products.name'
            ]);

        // Apply search filter
        if ($request->filled('search')) {
            $query->where('products.name', 'like', '%' . $request->search . '%');
        }

        // Apply year filter
        if ($request->filled('year')) {
            $query->whereExists(function ($subQuery) use ($request) {
                $subQuery->select(DB::raw(1))
                    ->from('warehouse_amcs')
                    ->whereColumn('warehouse_amcs.product_id', 'products.id')
                    ->where('warehouse_amcs.month_year', 'like', $request->year . '-%');
            });
        }

        // Apply sorting
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        
        $query->orderBy('products.name', $sortDirection);

        // Get all results (no pagination)
        $products = $query->get();

        // Determine if AMC should be shown (only for the latest year with data)
        $latestMonthYear = WarehouseAmc::max('month_year');
        $latestYear = $latestMonthYear ? substr($latestMonthYear, 0, 4) : null;
        $showAmc = $latestYear && (string) $selectedYear === (string) $latestYear;

        // Pre-calculate AMC for all products (last 12 months, 70% screening) if needed
        $amcResults = [];
        if ($showAmc && $products->isNotEmpty()) {
            $productIds = $products->pluck('id')->all();
            $amcService = app(WarehouseAmcCalculationService::class);
            $amcResults = $amcService->calculateAmcForProducts($productIds);
        }

        // Transform data to pivot table format
        $pivotData = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'name' => $product->name,
                'months' => []
            ];

            // Get consumption data for each month (per selected year)
            foreach ($monthYears as $monthYear) {
                $consumption = WarehouseAmc::where('product_id', $product->id)
                    ->where('month_year', $monthYear)
                    ->value('quantity') ?? 0;
                
                $row['months'][$monthYear] = $consumption;
            }

            // AMC: use dynamic AMC based on last 12 months (independent of year filter)
            // Only show for the latest year; older years get no AMC (frontend shows "-")
            if ($showAmc && isset($amcResults[$product->id])) {
                $row['amc'] = round($amcResults[$product->id]['amc'] ?? 0, 2);
            } else {
                $row['amc'] = null;
            }

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



        // We no longer restrict by existing AMC rows; all products are exported.
        $products = $query->orderBy('products.name')->get();

        // Determine if AMC should be shown (only for the latest year with data)
        $latestMonthYear = WarehouseAmc::max('month_year');
        $latestYear = $latestMonthYear ? substr($latestMonthYear, 0, 4) : null;
        $showAmc = $latestYear && (string) $selectedYear === (string) $latestYear;

        // Pre-calculate AMC for all products (last 12 months, 70% screening) if needed
        $amcResults = [];
        if ($showAmc && $products->isNotEmpty()) {
            $productIds = $products->pluck('id')->all();
            $amcService = app(WarehouseAmcCalculationService::class);
            $amcResults = $amcService->calculateAmcForProducts($productIds);
        }

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

            // AMC column in export: same dynamic AMC as the page, only for latest year
            if ($showAmc && isset($amcResults[$product->id])) {
                $row['AMC'] = round($amcResults[$product->id]['amc'] ?? 0, 2);
            } else {
                $row['AMC'] = null;
            }

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
            if (!$request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file was uploaded'
                ], 422);
            }

            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            if (!$file->isValid() || !in_array($extension, ['xlsx', 'xls', 'csv'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file type. Please upload an Excel file (.xlsx, .xls) or CSV file (.csv)'
                ], 422);
            }

            $importId = (string) Str::uuid();

            Cache::put("warehouse_amc_import_{$importId}", [
                'status' => 'processing',
                'progress' => 0,
                'message' => 'Import started'
            ], 3600);

            $fileSize = $file->getSize();
            $largeFileThreshold = 5 * 1024 * 1024; // 5MB

            if ($fileSize > $largeFileThreshold) {
                try {
                    $storedPath = $file->store('warehouse-amc-imports', 'local');
                    Excel::queueImport(new WarehouseAmcImport($importId, $storedPath), $storedPath)->onQueue('imports');
                    return response()->json([
                        'success' => true,
                        'message' => 'Large file detected. Import has been queued and will process in the background.',
                        'import_id' => $importId,
                        'queued' => true
                    ]);
                } catch (\Exception $e) {
                    // Fall through to synchronous import
                }
            }

            $import = new WarehouseAmcImport($importId);
            Excel::import($import, $file);
            $results = $import->getResults();

            $message = "Import completed successfully. ";
            if ($results['imported'] > 0) {
                $message .= "Created: {$results['imported']} new AMC records. ";
            }
            if ($results['updated'] > 0) {
                $message .= "Updated: {$results['updated']} existing AMC records. ";
            }
            if (!empty($results['errors'])) {
                $message .= count($results['errors']) . ' errors (e.g. product not found). ';
            }

            Cache::put("warehouse_amc_import_{$importId}", [
                'status' => 'completed',
                'progress' => 100,
                'message' => trim($message),
                'results' => $results
            ], 3600);

            return response()->json([
                'success' => true,
                'message' => trim($message),
                'import_id' => $importId,
                'results' => $results,
                'queued' => false
            ]);

        } catch (\Throwable $e) {
            if (isset($importId)) {
                Cache::put("warehouse_amc_import_{$importId}", [
                    'status' => 'failed',
                    'progress' => 0,
                    'message' => 'Import failed: ' . $e->getMessage()
                ], 3600);
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
            $selectedYear = $request->get('year', now()->year);
            $monthYears = collect();
            for ($month = 1; $month <= 12; $month++) {
                $monthYears->push($selectedYear . '-' . str_pad($month, 2, '0', STR_PAD_LEFT));
            }

            $allProducts = Product::select(['products.id', 'products.name'])
                ->orderBy('products.name')
                ->get();

            $templateData = [];
            foreach ($allProducts as $product) {
                $row = ['name' => $product->name];
                foreach ($monthYears as $monthYear) {
                    $row[$monthYear] = '';
                }
                $templateData[] = $row;
            }

            $filename = 'warehouse_amc_import_template_' . $selectedYear . '_' . now()->format('Y-m-d') . '.xlsx';
            return Excel::download(new WarehouseAmcExport($templateData, $monthYears, true), $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Template download failed: ' . $e->getMessage(),
            ], 500);
        }
    }

}
