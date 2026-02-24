<?php

namespace App\Http\Controllers;

use App\Mail\PhysicalCountSubmitted;
use App\Services\AmcCalculationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\AvarageMonthlyconsumption;
use App\Models\Location;
use App\Models\Product;
use App\Models\MonthlyQuantityReceived;
use App\Http\Resources\ReceivedQuantityResource;
use App\Models\MonthlyConsumptionReport;
use App\Models\MonthlyConsumptionItem;
use App\Models\PackingList;
use App\Models\Warehouse;
use App\Http\Resources\PackingListResource;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Collection;
use App\Models\Facility;
use App\Models\FacilityInventory;
use App\Models\FacilityInventoryItem;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\InventoryReport;
use App\Models\InventoryReportItem;
use App\Models\WarehouseAmc;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\ReceivedBackorder;
use App\Models\ReceivedBackorderItem;
use App\Models\Liquidate;
use App\Models\LiquidateItem;
use App\Jobs\ProcessPhysicalCountApprovalJob;
use App\Models\IssueQuantityReport;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\PhysicalCountReportResource;
use App\Models\Disposal;
use App\Models\Liquidation;
use App\Models\Supply;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\Transfer;
use App\Http\Resources\DisposalResource;
use App\Models\IssueQuantityItem;
use App\Models\ReceivedQuantityItem;
use App\Models\IssuedQuantity;
use App\Models\ReceivedQuantity;
use App\Models\District;
use App\Models\Region;
use App\Models\Reason;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Jobs\ImportIssueQuantityJob;
use App\Imports\IssueQuantitiyImport;
use App\Models\FacilityMonthlyReport;
use App\Jobs\ProcessIssueQuantityImport;
use App\Exports\WarehouseMonthlyReportExport;
use App\Models\PhysicalCountReport;

class ReportController extends Controller
{
    public function importIssueQuantity(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'month_year' => 'required|date_format:Y-m'
        ]);
    
        $file = $request->file('file');
        $monthYear = $request->input('month_year');
        $userId = auth()->id();
    
        // Store the file temporarily
        $path = $file->store('temp');
    
        // Dispatch the job
        ProcessIssueQuantityImport::dispatch($path, $monthYear, $userId)
            ->onQueue('imports');
    
        return back()->with('success', 'Import has been queued and will be processed shortly.');
    }

    public function index(Request $request){
        return inertia('Report/Index');
    } 

    public function updatePhysicalCountReport(Request $request){
        try {
            $request->validate([
                'id' => 'required|exists:inventory_adjustments,id',
                'items' => 'required|array',
                'items.*.id' => 'required|exists:inventory_adjustment_items,id',
                'items.*.physical_count' => 'required|numeric',
                'items.*.difference' => 'required',
                'items.*.remarks' => 'nullable',
            ]);
            
            return DB::transaction(function () use ($request) {
                $adjustment = InventoryAdjustment::findOrFail($request->id);
                
                // Process items in chunks to avoid memory issues and timeouts
                $chunkSize = 10; // Process 10 items at a time
                $items = collect($request->items);
                
                $items->chunk($chunkSize)->each(function ($chunk) {
                    // Get all IDs in this chunk
                    $chunkIds = $chunk->pluck('id')->toArray();
                    
                    // Get all adjustment items for this chunk in one query
                    $adjustmentItems = InventoryAdjustmentItem::whereIn('id', $chunkIds)->get()->keyBy('id');
                    
                    // Update each item in the chunk
                    foreach ($chunk as $item) {
                        if (isset($adjustmentItems[$item['id']])) {
                            $adjustmentItems[$item['id']]->update([
                                'physical_count' => $item['physical_count'],
                                'difference' => $item['difference'],
                                'remarks' => $item['remarks'] ?? null
                            ]);
                        }
                    }
                });
                
                $adjustment->update([
                    'status' => 'submitted'
                ]);

                // Send email notification to users with report.physical-count-review permission
                $users = User::permission('report.physical-count-review')->get();
                $approvalLink = route('reports.physicalCount', ['month_year' => $adjustment->month_year]);
                $submittedBy = Auth::user();

                foreach ($users as $user) {
                    Mail::to($user->email)
                        ->queue(new PhysicalCountSubmitted($adjustment, $approvalLink, $submittedBy));
                }

                return response()->json("Physical count submitted successfully", 200);
            });
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function inventoryReport(Request $request){
        // Get warehouses for the filter
        $warehouses = Warehouse::select('id', 'name')->orderBy('name')->get();

        // Get the current month and year for default filtering
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');

        return Inertia::render('Report/InventoryReport', [
            'warehouses' => $warehouses,
            'currentMonth' => $currentMonth,
            'currentYear' => $currentYear,
        ]);
    }

    public function generateInventoryReport(Request $request){
        try {
            $monthYear = $request->input('month_year');
            $warehouseId = $request->input('warehouse_id');

            // Generate the inventory report using the existing getInventoryReportData method
            $reportData = $this->getInventoryReportData($request, $monthYear);

            return response()->json([
                'success' => true,
                'message' => 'Inventory report generated successfully',
                'data' => $reportData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate inventory report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function inventoryReportData(Request $request){
        try {
            $monthYear = $request->input('month_year');
            $prevMonthYear = $request->input('prev_month_year');
            $warehouseId = $request->input('warehouse_id');

            // Log the request parameters for debugging
            Log::info('Inventory Report Data Request', [
                'month_year' => $monthYear,
                'warehouse_id' => $warehouseId,
                'prev_month_year' => $prevMonthYear
            ]);

            // Get the inventory report data
            $reportData = $this->getInventoryReportData($request, $monthYear);

            // Log the data count for debugging
            Log::info('Inventory Report Data Retrieved', [
                'count' => $reportData->count(),
                'month_year' => $monthYear,
                'warehouse_id' => $warehouseId
            ]);

            // Get previous month data for comparison
            $prevReportData = [];
            if ($prevMonthYear) {
                $prevRequest = new Request(['month_year' => $prevMonthYear, 'warehouse_id' => $warehouseId]);
                $prevReportData = $this->getInventoryReportData($prevRequest, $prevMonthYear);
            }

            return response()->json([
                'success' => true,
                'data' => $reportData,
                'prevData' => $prevReportData,
                'debug' => [
                    'month_year' => $monthYear,
                    'warehouse_id' => $warehouseId,
                    'count' => $reportData->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Inventory Report Data Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch inventory report data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consolidated Inventory Reports page: single view with Report Type filter that combines
     * QTY Received, QTY Issued, Physical Count, Warehouse Inventory, Warehouse AMC, Facility Monthly Consumption.
     */
    public function inventoryReportsUnified(Request $request)
    {
        $regions = Region::orderBy('name')->get(['id', 'name']);
        $districts = District::orderBy('name')->get(['id', 'name', 'region']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name', 'region', 'district']);
        $facilities = Facility::orderBy('name')->get(['id', 'name', 'region', 'district']);
        $reportTypes = [
            ['value' => 'warehouse_inventory', 'label' => 'Warehouse Inventory Report'],
            ['value' => 'qty_received', 'label' => 'QTY Received Report'],
            ['value' => 'qty_issued', 'label' => 'QTY Issued Report'],
            ['value' => 'physical_count', 'label' => 'Physical Count Report'],
            ['value' => 'warehouse_amc', 'label' => 'Warehouse AMC Report'],
            ['value' => 'facility_monthly_consumption', 'label' => 'Facility Monthly Consumption'],
        ];
        return Inertia::render('Report/InventoryReportsUnified', [
            'regions' => $regions,
            'districts' => $districts,
            'warehouses' => $warehouses,
            'facilities' => $facilities,
            'reportTypes' => $reportTypes,
            'filters' => $request->only(['region_id', 'district_id', 'warehouse_id', 'facility_id', 'report_type', 'year', 'month']),
        ]);
    }

    /**
     * Data endpoint for consolidated inventory reports. Returns unified rows based on report_type and filters.
     */
    public function inventoryReportsUnifiedData(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:warehouse_inventory,qty_received,qty_issued,physical_count,warehouse_amc,facility_monthly_consumption',
            'region_id' => 'nullable|exists:regions,id',
            'district_id' => 'nullable|exists:districts,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'facility_id' => 'nullable|exists:facilities,id',
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $hasLocationFilter = $request->filled('region_id')
            || $request->filled('district_id')
            || $request->filled('warehouse_id')
            || $request->filled('facility_id');
        if (!$hasLocationFilter) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Please select at least one filter (Region, District, Warehouse or Facility).',
            ]);
        }

        $reportType = $request->report_type;
        $monthYear = null;
        if ($request->filled('year') && $request->filled('month')) {
            $monthYear = sprintf('%04d-%02d', (int) $request->year, (int) $request->month);
        } elseif ($request->filled('year')) {
            $monthYear = (string) $request->year;
        }
        $warehouseIds = $this->resolveWarehouseIdsFromFilters($request);
        $facilityIds = $this->resolveFacilityIdsFromFilters($request);
        $data = $this->getUnifiedInventoryReportRows($reportType, $monthYear, $request->warehouse_id, $request->facility_id, $warehouseIds, $facilityIds, $request);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Resolve warehouse IDs for filtering (by region, district, or single warehouse).
     * Returns [] when no filter is applied (show all).
     */
    private function resolveWarehouseIdsFromFilters(Request $request): array
    {
        if ($request->filled('warehouse_id')) {
            return [(int) $request->warehouse_id];
        }
        if (!$request->filled('region_id') && !$request->filled('district_id')) {
            return [];
        }
        $query = Warehouse::query();
        if ($request->filled('region_id')) {
            $regionName = Region::find($request->region_id)?->name;
            if ($regionName) {
                $query->where('region', $regionName);
            }
        }
        if ($request->filled('district_id')) {
            $districtName = District::find($request->district_id)?->name;
            if ($districtName) {
                $query->where('district', $districtName);
            }
        }
        return $query->pluck('id')->toArray();
    }

    /**
     * Resolve facility IDs for filtering. Returns [] when no filter (show all).
     */
    private function resolveFacilityIdsFromFilters(Request $request): array
    {
        if ($request->filled('facility_id')) {
            return [(int) $request->facility_id];
        }
        if (!$request->filled('region_id') && !$request->filled('district_id')) {
            return [];
        }
        $query = Facility::query();
        if ($request->filled('region_id')) {
            $regionName = Region::find($request->region_id)?->name;
            if ($regionName) {
                $query->where('region', $regionName);
            }
        }
        if ($request->filled('district_id')) {
            $districtName = District::find($request->district_id)?->name;
            if ($districtName) {
                $query->where('district', $districtName);
            }
        }
        return $query->pluck('id')->toArray();
    }

    /**
     * Build unified report rows for the consolidated inventory report table.
     */
    private function getUnifiedInventoryReportRows(
        string $reportType,
        ?string $monthYear,
        $warehouseId,
        $facilityId,
        array $warehouseIds,
        array $facilityIds,
        Request $request
    ): array {
        $rows = [];
        switch ($reportType) {
            case 'warehouse_inventory':
                $rows = $this->unifiedRowsFromWarehouseInventory($monthYear, $warehouseIds);
                break;
            case 'qty_received':
                $rows = $this->unifiedRowsFromQtyReceived($monthYear, $warehouseIds);
                break;
            case 'qty_issued':
                $rows = $this->unifiedRowsFromQtyIssued($monthYear, $warehouseIds);
                break;
            case 'physical_count':
                $rows = $this->unifiedRowsFromPhysicalCount($monthYear, $warehouseIds);
                break;
            case 'warehouse_amc':
                $rows = $this->unifiedRowsFromWarehouseAmc($monthYear);
                break;
            case 'facility_monthly_consumption':
                $rows = $this->unifiedRowsFromFacilityMonthlyConsumption($monthYear, $facilityIds, $request);
                break;
        }
        return $rows;
    }

    private function unifiedRowsFromWarehouseInventory(?string $monthYear, array $warehouseIds): array
    {
        if (!$monthYear) {
            return [];
        }
        $report = InventoryReport::where('month_year', $monthYear)->first();
        if (!$report) {
            return [];
        }
        $query = $report->items()->with([
            'product' => fn ($q) => $q->select('id', 'name', 'category_id', 'dosage_id')->with(['category:id,name', 'dosage:id,name']),
            'warehouse:id,name',
        ]);
        if (!empty($warehouseIds)) {
            $query->whereIn('warehouse_id', $warehouseIds);
        }
        $items = $query->get();
        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'item' => $item->product->name ?? '',
                'category' => $item->product->category->name ?? '',
                'uom' => $item->product->dosage->name ?? '',
                'batch_no' => null,
                'expiry_date' => null,
                'beginning_balance' => (int) $item->beginning_balance,
                'qty_received' => (int) $item->received_quantity,
                'qty_issued' => (int) $item->issued_quantity,
                'adjustment_neg' => (int) $item->negative_adjustment,
                'adjustment_pos' => (int) $item->positive_adjustment,
                'closing_balance' => (int) $item->closing_balance,
                'total_closing_balance' => (int) $item->total_closing_balance,
                'amc' => (int) ($item->average_monthly_consumption ?? 0),
                'mos' => $item->months_of_stock ?? '',
                'stockout_days' => 0,
                'unit_cost' => (float) ($item->unit_cost ?? 0),
                'total_cost' => (float) ($item->total_cost ?? 0),
                'warehouse_name' => $item->warehouse->name ?? '',
                'facility_name' => null,
            ];
        }
        return $rows;
    }

    private function unifiedRowsFromQtyReceived(?string $monthYear, array $warehouseIds): array
    {
        if (!$monthYear) {
            return [];
        }
        $query = MonthlyQuantityReceived::with([
            'items.product' => fn ($q) => $q->select('id', 'name', 'category_id', 'dosage_id')->with(['category:id,name', 'dosage:id,name']),
            'items.warehouse:id,name',
        ])->where('month_year', 'like', $monthYear . '%');
        $reports = $query->get();
        $rows = [];
        foreach ($reports as $report) {
            foreach ($report->items as $item) {
                if (!empty($warehouseIds) && !in_array($item->warehouse_id, $warehouseIds)) {
                    continue;
                }
                $rows[] = [
                    'item' => $item->product->name ?? '',
                    'category' => $item->product->category->name ?? '',
                    'uom' => $item->product->dosage->name ?? '',
                    'batch_no' => null,
                    'expiry_date' => null,
                    'beginning_balance' => 0,
                    'qty_received' => (int) $item->quantity,
                    'qty_issued' => 0,
                    'adjustment_neg' => 0,
                    'adjustment_pos' => 0,
                    'closing_balance' => 0,
                    'total_closing_balance' => 0,
                    'amc' => 0,
                    'mos' => null,
                    'stockout_days' => 0,
                    'unit_cost' => 0,
                    'total_cost' => 0,
                    'warehouse_name' => $item->warehouse->name ?? '',
                    'facility_name' => null,
                ];
            }
        }
        return $rows;
    }

    private function unifiedRowsFromQtyIssued(?string $monthYear, array $warehouseIds): array
    {
        if (!$monthYear) {
            return [];
        }
        $query = IssueQuantityReport::with([
            'items.product' => fn ($q) => $q->select('id', 'name', 'category_id', 'dosage_id')->with(['category:id,name', 'dosage:id,name']),
            'items.warehouse:id,name',
        ])->where('month_year', 'like', $monthYear . '%');
        $reports = $query->get();
        $rows = [];
        foreach ($reports as $report) {
            foreach ($report->items as $item) {
                if (!empty($warehouseIds) && !in_array($item->warehouse_id, $warehouseIds)) {
                    continue;
                }
                $rows[] = [
                    'item' => $item->product->name ?? '',
                    'category' => $item->product->category->name ?? '',
                    'uom' => $item->product->dosage->name ?? '',
                    'batch_no' => null,
                    'expiry_date' => null,
                    'beginning_balance' => 0,
                    'qty_received' => 0,
                    'qty_issued' => (int) $item->quantity,
                    'adjustment_neg' => 0,
                    'adjustment_pos' => 0,
                    'closing_balance' => 0,
                    'total_closing_balance' => 0,
                    'amc' => 0,
                    'mos' => null,
                    'stockout_days' => 0,
                    'unit_cost' => 0,
                    'total_cost' => 0,
                    'warehouse_name' => $item->warehouse->name ?? '',
                    'facility_name' => null,
                ];
            }
        }
        return $rows;
    }

    private function unifiedRowsFromPhysicalCount(?string $monthYear, array $warehouseIds): array
    {
        if (!$monthYear) {
            return [];
        }
        $adjustment = InventoryAdjustment::where('month_year', 'like', $monthYear . '%')
            ->whereIn('status', ['pending', 'reviewed', 'submitted'])
            ->first();
        if (!$adjustment) {
            return [];
        }
        $items = $adjustment->items()
            ->with([
                'product' => fn ($q) => $q->select('id', 'name', 'category_id', 'dosage_id')->with(['category:id,name', 'dosage:id,name']),
                'warehouse:id,name',
            ])
            ->get();
        $rows = [];
        foreach ($items as $item) {
            if (!empty($warehouseIds) && $item->warehouse_id && !in_array($item->warehouse_id, $warehouseIds)) {
                continue;
            }
            $rows[] = [
                'item' => $item->product->name ?? '',
                'category' => $item->product->category->name ?? '',
                'uom' => $item->uom ?? ($item->product->dosage->name ?? ''),
                'batch_no' => $item->batch_number ?? null,
                'expiry_date' => $item->expiry_date ? $item->expiry_date->format('Y-m-d') : null,
                'beginning_balance' => 0,
                'qty_received' => 0,
                'qty_issued' => 0,
                'adjustment_neg' => 0,
                'adjustment_pos' => 0,
                'closing_balance' => (int) ($item->physical_count ?? 0),
                'total_closing_balance' => (int) ($item->physical_count ?? 0),
                'amc' => 0,
                'mos' => null,
                'stockout_days' => 0,
                'unit_cost' => (float) ($item->unit_cost ?? 0),
                'total_cost' => (float) ($item->total_cost ?? 0),
                'warehouse_name' => $item->warehouse->name ?? '',
                'facility_name' => null,
            ];
        }
        return $rows;
    }

    private function unifiedRowsFromWarehouseAmc(?string $monthYear): array
    {
        if (!$monthYear) {
            return [];
        }
        $query = WarehouseAmc::with([
            'product' => fn ($q) => $q->select('id', 'name', 'category_id', 'dosage_id')->with(['category:id,name', 'dosage:id,name']),
        ])->where('month_year', 'like', $monthYear . '%');
        $items = $query->get();
        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'item' => $item->product->name ?? '',
                'category' => $item->product->category->name ?? '',
                'uom' => $item->product->dosage->name ?? '',
                'batch_no' => null,
                'expiry_date' => null,
                'beginning_balance' => 0,
                'qty_received' => 0,
                'qty_issued' => 0,
                'adjustment_neg' => 0,
                'adjustment_pos' => 0,
                'closing_balance' => 0,
                'total_closing_balance' => 0,
                'amc' => (int) $item->quantity,
                'mos' => null,
                'stockout_days' => 0,
                'unit_cost' => 0,
                'total_cost' => 0,
                'warehouse_name' => '',
                'facility_name' => null,
            ];
        }
        return $rows;
    }

    /**
     * Facility (monthly) consumption / inventory: uses facility_inventory and facility_inventory_items.
     */
    private function unifiedRowsFromFacilityMonthlyConsumption(?string $monthYear, array $facilityIds, Request $request): array
    {
        if (empty($facilityIds)) {
            $facilityId = $request->facility_id;
            if (!$facilityId) {
                return [];
            }
            $facilityIds = [(int) $facilityId];
        }
        $query = FacilityInventoryItem::with([
            'product' => fn ($q) => $q->select('id', 'name', 'category_id', 'dosage_id')->with(['category:id,name', 'dosage:id,name']),
            'inventory.facility:id,name',
        ])->whereHas('inventory', fn ($q) => $q->whereIn('facility_id', $facilityIds));
        $items = $query->get();
        $rows = [];
        foreach ($items as $item) {
            $facilityName = $item->inventory->facility->name ?? null;
            $rows[] = [
                'item' => $item->product->name ?? '',
                'category' => $item->product->category->name ?? '',
                'uom' => $item->uom ?? ($item->product->dosage->name ?? ''),
                'batch_no' => $item->batch_number ?? null,
                'expiry_date' => $item->expiry_date ? $item->expiry_date->format('Y-m-d') : null,
                'beginning_balance' => 0,
                'qty_received' => 0,
                'qty_issued' => 0,
                'adjustment_neg' => 0,
                'adjustment_pos' => 0,
                'closing_balance' => (int) ($item->quantity ?? 0),
                'total_closing_balance' => (int) ($item->quantity ?? 0),
                'amc' => 0,
                'mos' => null,
                'stockout_days' => 0,
                'unit_cost' => (float) ($item->unit_cost ?? 0),
                'total_cost' => (float) ($item->total_cost ?? 0),
                'warehouse_name' => null,
                'facility_name' => $facilityName,
            ];
        }
        return $rows;
    }
    
    public function physicalCountReport(Request $request){
        $monthYear = $request->input('month_year', date('Y-m'));
        
        // Check if there's an existing adjustment for this month
        $adjustment = InventoryAdjustment::where('month_year', $monthYear)
            ->with(['reviewer:id,name', 'approver:id,name', 'rejecter:id,name'])
            ->whereIn('status', ['pending', 'reviewed','submitted'])
            ->first();
        
        $adjustmentData = [];
        
        if ($adjustment) {
            // Get all adjustment items with their related product information
            $items = $adjustment->items()
                ->with([
                    'product' => function($query) {
                        $query->select('id', 'name');
                    },
                    'product',
                    'warehouse:id,name',
                ])
                ->get();
                
            $adjustmentData = [
                'id' => $adjustment->id,
                'month_year' => $adjustment->month_year,
                'adjustment_date' => $adjustment->adjustment_date,
                'status' => $adjustment->status,
                'items' => $items
            ];
        }
        
        return inertia('Report/PhysicalCountReport', [
            'physicalCountReport' => $adjustmentData,
            'currentMonthYear' => $monthYear,
        ]);
    }

    public function issueQuantityReports(Request $request)
    {
        $query = IssueQuantityReport::query()
            ->with(['items.product.dosage', 'items.product.category']);

        // Handle multiple date filters (year and month combinations)
        if ($request->filled('date_filters') && is_array($request->date_filters)) {
            $query->where(function($q) use ($request) {
                foreach ($request->date_filters as $dateFilter) {
                    // If it's a full year-month format (YYYY-MM)
                    if (strlen($dateFilter) === 7) {
                        $q->orWhere('month_year', 'like', $dateFilter . '%');
                    } 
                    // If it's just a year (YYYY)
                    else if (strlen($dateFilter) === 4) {
                        $q->orWhere('month_year', 'like', $dateFilter . '%');
                    }
                }
            });
        }
        
        // Backward compatibility for old filter format
        else if ($request->filled('month')) {
            $query->where('month_year', 'like', $request->month . '%');
        }

        $issueQuantityReports = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();

        $issueQuantityReports->setPath(url()->current()); // Force Laravel to use full URLs

        return inertia('Report/IssueQuantityReports', [
            'issueQuantityReports' => $issueQuantityReports,
            'products' => Product::orderBy('name')->get(),
            'filters' => $request->only(['month', 'per_page']),
        ]);
    }

    public function exportIssueQuantityReports(Request $request)
    {
        $query = IssueQuantityReport::query()
            ->with(['items.product.dosage', 'items.product.category', 'items.warehouse']);

        // Apply date filters
        if ($request->filled('date_filters') && is_array($request->date_filters)) {
            $query->where(function($q) use ($request) {
                foreach ($request->date_filters as $dateFilter) {
                    if (strlen($dateFilter) === 7) { // YYYY-MM
                        $q->orWhere('month_year', 'like', $dateFilter . '%');
                    } else if (strlen($dateFilter) === 4) { // YYYY
                        $q->orWhere('month_year', 'like', $dateFilter . '%');
                    }
                }
            });
        }

        $reports = $query->get();

        // Create Excel file
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $sheet->setCellValue('A1', 'Month/Year');
        $sheet->setCellValue('B1', 'Total Quantity');
        $sheet->setCellValue('C1', 'Generated By');
        $sheet->setCellValue('D1', 'Generated At');

        // Set data
        $row = 2;
        foreach ($reports as $report) {
            $sheet->setCellValue('A' . $row, $report->month_year);
            $sheet->setCellValue('B' . $row, $report->total_quantity);
            $sheet->setCellValue('C' . $row, $report->generated_by);
            $sheet->setCellValue('D' . $row, $report->created_at);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Create response
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'issue_quantities_report_' . date('Y-m-d') . '.xlsx';

        // Save to temp file and return
        $tempFile = tempnam(sys_get_temp_dir(), $filename);
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // The exportIssueQuantityReportItems method has been removed as this functionality
    // is now handled client-side using the XLSX library in the Vue component
    
    // mnthly consumption by facilities [AMC]

    public function receivedQuantities(Request $request)
    {
        $query = MonthlyQuantityReceived::query()
            ->with(['items.product.dosage','items.product.category']);

        // Apply filters
        // Warehouse filter removed as warehouse_id doesn't exist in the product table

        // Handle multiple date filters (year and month combinations)
        if ($request->filled('date_filters') && is_array($request->date_filters)) {
            $query->where(function($q) use ($request) {
                foreach ($request->date_filters as $dateFilter) {
                    // If it's a full year-month format (YYYY-MM)
                    if (strlen($dateFilter) === 7) {
                        $q->orWhere('month_year', 'like', $dateFilter . '%');
                    } 
                    // If it's just a year (YYYY)
                    else if (strlen($dateFilter) === 4) {
                        $q->orWhere('month_year', 'like', $dateFilter . '%');
                    }
                }
            });
        } 
        // Backward compatibility for old filter format
        else if ($request->filled('month')) {
            $query->where('month_year', 'like', $request->month . '%');
        }
        
        $receivedQuantities = $query->paginate($request->input('per_page', 1), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();

            $receivedQuantities->setPath(url()->current()); // Force Laravel to use full URLs
        

        return inertia('Report/ReceivedQuantities', [
            'receivedQuantities' => ReceivedQuantityResource::collection($receivedQuantities),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'filters' => $request->only(['month', 'per_page']),
        ]);
    }

    public function monthlyConsumption(Request $request)
    {
        $facilityId = $request->input('facility_id');
        // Product filtering is now handled client-side
        $startMonth = $request->input('start_month', Carbon::now()->startOfYear()->format('Y-m'));
        $endMonth = $request->input('end_month', Carbon::now()->format('Y-m'));
        $isSubmitted = $request->input('is_submitted', false);
        
        $monthlyConsumptionReport = [];
        $amcByProduct = [];
        // Only fetch data if the form has been submitted with valid filters
        if ($isSubmitted && $facilityId && $startMonth && $endMonth) {
            $monthlyConsumptionReport = MonthlyConsumptionReport::where('facility_id', $facilityId)
                ->with('facility.user','items.product')
                ->whereBetween('month_year', [$startMonth, $endMonth])
                ->get();

            // Collect product ids present in the fetched reports
            $productIds = [];
            foreach ($monthlyConsumptionReport as $report) {
                foreach ($report->items as $item) {
                    $productIds[$item->product_id] = true;
                }
            }

            $productIds = array_keys($productIds);

            if (!empty($productIds)) {
                try {
                    // Increase execution time for AMC calculation
                    set_time_limit(120); // 2 minutes
                    
                    // Use the new AMC calculation service
                    $amcService = new AmcCalculationService();
                    $amcByProduct = $amcService->calculateAmcForProducts($facilityId, $productIds);
                    
                } catch (\Exception $e) {
                    // Log the error and provide fallback
                    \Log::error('AMC calculation failed: ' . $e->getMessage(), [
                        'facility_id' => $facilityId,
                        'product_count' => count($productIds),
                        'error' => $e->getMessage()
                    ]);
                    
                    // Provide empty AMC data as fallback
                    $amcByProduct = [];
                    foreach ($productIds as $productId) {
                        $amcByProduct[$productId] = [
                            'amc' => 0,
                            'selectedMonths' => [],
                            'totalMonths' => 0,
                            'calculation' => 'AMC calculation failed - please try again'
                        ];
                    }
                }
            }
        }

        return inertia('Report/MonthlyConsumption', [
            'pivotData' => $monthlyConsumptionReport,
            'amcByProduct' => $amcByProduct,
            'facilities' => Facility::select('id', 'name', 'facility_type')->get(),
            'products' => Product::select('id', 'name')->get(),
            'facilityInfo' => null,
            'filters' => [
                'facility_id' => $facilityId,
                'start_month' => $startMonth,
                'end_month' => $endMonth
            ]
        ]);
    }

    public function getTemplateProducts(Request $request)
    {
        try {
            $facilityId = $request->input('facility_id');
            
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required'
                ], 400);
            }

            // Get the facility and its facility_type
            $facility = Facility::find($facilityId);
            
            if (!$facility) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility not found'
                ], 404);
            }

            // Get eligible products for this facility type
            $eligibleProducts = Product::whereHas('eligible', function ($query) use ($facility) {
                $query->where('facility_type', $facility->facility_type);
            })
            ->where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

            return response()->json([
                'success' => true,
                'products' => $eligibleProducts,
                'facility' => [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'facility_type' => $facility->facility_type
                ],
                'count' => $eligibleProducts->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching template products: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching eligible products'
            ], 500);
        }
    }

    public function generatePhysicalCountReport(Request $request)
    {
        try {
            // Check if there's already a pending or reviewed adjustment
            $existingAdjustment = InventoryAdjustment::whereIn('status', ['pending', 'reviewed'])
                ->first();
            
            if ($existingAdjustment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot generate new physical count adjustment. There is already a ' . $existingAdjustment->status . ' adjustment from ' . Carbon::parse($existingAdjustment->adjustment_date)->format('M d, Y') . ' that needs to be processed or rejected first.'
                ], 400);
            }
            
            DB::beginTransaction();
            
            // Create parent adjustment record
            $adjustment = InventoryAdjustment::create([
                'month_year' => date('Y-m'),
                'adjustment_date' => Carbon::now(),
                'status' => 'pending',
            ]);
            
            // Get all inventory items with active products
            $inventoryItems = InventoryItem::whereHas('product', function($query) {
                $query->where('is_active', true);
            })->get();
            
            // Create adjustment items for each inventory item
            foreach ($inventoryItems as $inventoryItem) {
                // Get the most recent unit cost for this product if current one is 0 or null
                $unitCost = $inventoryItem->unit_cost;
                if (!$unitCost || $unitCost == 0) {
                    // Look for any recent inventory item with valid unit cost for this product
                    $recentInventoryItem = InventoryItem::where('product_id', $inventoryItem->product_id)
                        ->orderBy('updated_at', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    $unitCost = $recentInventoryItem ? $recentInventoryItem->unit_cost : 0;
                }
                
                InventoryAdjustmentItem::create([
                    'parent_id' => $adjustment->id,
                    'user_id' => auth()->id(),
                    'product_id' => $inventoryItem->product_id,
                    'warehouse_id' => $inventoryItem->warehouse_id,
                    'quantity' => $inventoryItem->quantity,
                    'physical_count' => 0, // Default to 0, will be updated during physical count
                    'batch_number' => $inventoryItem->batch_number ?? 'N/A',
                    'barcode' => $inventoryItem->barcode,
                    'location' => $inventoryItem->location,
                    'unit_cost' => $unitCost,
                    'total_cost' => $inventoryItem->quantity * $unitCost,
                    'expiry_date' => $inventoryItem->expiry_date,
                    'uom' => $inventoryItem->uom,
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Physical count adjustment has been successfully generated.'
            ]);
            
        } catch (\Throwable $th) {
            DB::rollBack();

            logger()->error('Physical count adjustment generation error: ' . $th->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating the physical count adjustment: ' . $th->getMessage()
            ], 500);
        }
    }

    public function updatePhysicalCountStatus(Request $request){
        try {
            $request->validate([
                'id' => 'required|exists:inventory_adjustments,id',
                'status' => 'required|in:reviewed,approved'
            ]);
            return DB::transaction(function () use ($request) {
                $adjustment = InventoryAdjustment::findOrFail($request->id);
                $adjustment->update([
                    'status' => $request->status,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now()
                ]);
                return response()->json("Physical count status updated successfully", 200);
            });
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function approvePhysicalCountReport(Request $request){
        try {
            $request->validate([
                'id' => 'required|exists:inventory_adjustments,id',
                'status' => 'required|in:approved'
            ]);
            
            $adjustment = InventoryAdjustment::findOrFail($request->id);
            if($adjustment->status !== 'reviewed') {
                return response()->json("Physical count status must be reviewed before approval", 500);
            }

                          // Get the warehouse_id from the first adjustment item (all items should have the same warehouse)
             $firstAdjustmentItem = InventoryAdjustmentItem::where('parent_id', $adjustment->id)->first();
             $warehouseId = $firstAdjustmentItem ? $firstAdjustmentItem->warehouse_id : auth()->user()->warehouse_id;
             
             $receivedBackorder = ReceivedBackorder::create([
                 'received_by' => Auth::id(),
                 'received_at' => now(),
                 'status' => 'pending',
                 'type' => 'physical_count_adjustment',
                 'warehouse_id' => $warehouseId,
                 'inventory_adjustment_id' => $adjustment->id,
                 'note' => 'Physical count adjustment - positive difference'
             ]);
            
                         // Dispatch the job to process in background
             // Don't change status here - let the job handle it
             Log::info("Dispatching ProcessPhysicalCountApprovalJob", [
                 'adjustment_id' => $adjustment->id,
                 'user_id' => Auth::id(),
                 'received_backorder_id' => $receivedBackorder->id
             ]);
             
             ProcessPhysicalCountApprovalJob::dispatch($adjustment->id, Auth::id(), $receivedBackorder->id);
            
            return response()->json([
                'message' => 'Physical count approval has been queued for processing. You will be notified when it completes.',
                'status' => 'queued'
            ], 200);
            
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function rejectPhysicalCountReport(Request $request){
        try {
            $request->validate([
                'id' => 'required|exists:inventory_adjustments,id',
                'status' => 'required|in:rejected',
                'rejection_reason' => 'required'
            ]);
            return DB::transaction(function () use ($request) {
                $adjustment = InventoryAdjustment::findOrFail($request->id);
                if($adjustment->status !== 'reviewed') {
                    return response()->json("Physical count status must be reviewed before rejection", 500);
                }
                $adjustment->update([
                    'status' => $request->status,
                    'rejected_by' => Auth::id(),
                    'rejected_at' => now(),
                    'rejection_reason' => $request->rejection_reason
                ]);
                return response()->json("Physical count marked as rejected.", 200);
            });
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function rollBackRejectPhysicalCountReport(Request $request){
        try {
            $request->validate([
                'id' => 'required|exists:inventory_adjustments,id',
                'status' => 'required|in:pending'
            ]);
            return DB::transaction(function () use ($request) {
                $adjustment = InventoryAdjustment::findOrFail($request->id);
                if($adjustment->status !== 'rejected') {
                    return response()->json("Physical count status must be rejected before rollback", 500);
                }
                $adjustment->update([
                    'status' => $request->status,
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null
                ]);
                return response()->json("Physical count marked as pending.", 200);
            });
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }   

    public function physicalCountShow(Request $request){
        $physicalCountReport = InventoryAdjustment::query()
            ->when($request->filled('month'), function($query) use ($request) {
                $query->where('month_year', $request->month);
            })
            ->whereIn('status', ['approved', 'rejected'])
            ->with(['items.product.dosage', 'items.product.category', 'items.warehouse', 'approvedBy', 'rejectedBy', 'reviewedBy'])
            ->paginate($request->input('per_page', 100), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
            
        $physicalCountReport->setPath(url()->current());
        
        return inertia('Report/PhysicalCountShow', [
            'physicalCountReport' => PhysicalCountReportResource::collection($physicalCountReport),
            'filters' => $request->only(['month', 'per_page', 'page']),
        ]);
    }
    
    public function disposals(Request $request){
        logger()->info($request->all());
        $disposals = Disposal::query()
            ->when($request->filled('month'), function($query) use ($request) {
                $date = Carbon::createFromFormat('Y-m', $request->month);
                $query->whereYear('disposed_at', $date->year)
                      ->whereMonth('disposed_at', $date->month);
            })
            ->whereIn('status', ['approved', 'rejected'])
            ->with(['product.dosage', 'product.category', 'approvedBy', 'rejectedBy', 'reviewedBy','disposedBy'])
            ->paginate($request->input('per_page', 2), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        
        $disposals->setPath(url()->current());
        
        return inertia('Report/Disposals', [
            'disposals' => DisposalResource::collection($disposals),
            'filters' => $request->only(['month', 'per_page', 'page']),
        ]);
    }
    
    /**
     * Generate inventory report data for the given month and year
     *
     * @param Request $request
     * @param string $monthYear Format: Y-m
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getInventoryReportData(Request $request, $monthYear)
    {
        try {
            // Get or create the inventory report
            $inventoryReport = InventoryReport::firstOrCreate(
                ['month_year' => $monthYear],
                [
                    'status' => 'pending',
                    'generated_by' => auth()->id(),
                    'generated_at' => now(),
                ]
            );

            Log::info('Inventory Report Found/Created', [
                'id' => $inventoryReport->id,
                'month_year' => $monthYear,
                'status' => $inventoryReport->status
            ]);

            // Build query for items with relationships
            $query = $inventoryReport->items()
                ->with([
                    'product' => function($query) {
                        $query->select('id', 'name', 'category_id')
                            ->with('category:id,name');
                    },
                    'warehouse' => function($query) {
                        $query->select('id', 'name');
                    }
                ]);

            // Apply warehouse filter if provided
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->input('warehouse_id'));
            }

            // Return the filtered items
            $items = $query->get();
            
            Log::info('Inventory Report Items Retrieved', [
                'count' => $items->count(),
                'warehouse_filter' => $request->input('warehouse_id')
            ]);

            return $items;
        } catch (\Exception $e) {
            Log::error('Error in getInventoryReportData', [
                'error' => $e->getMessage(),
                'month_year' => $monthYear,
                'warehouse_id' => $request->input('warehouse_id'),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function warehouseMonthlyReport(Request $request)
    {
        try {
            $monthYear = $request->input('month_year', Carbon::now()->format('Y-m'));
            
            // Get warehouses for the filter
            $warehouses = Warehouse::select('id', 'name')->orderBy('name')->get();
            
            // Get inventory report status
            $inventoryReport = InventoryReport::with('submittedBy', 'reviewedBy', 'approvedBy', 'rejectedBy')
                ->where('month_year', $monthYear)
                ->firstOrCreate(
                    ['month_year' => $monthYear],
                    [
                        'status' => 'pending',
                        'generated_by' => auth()->id(),
                        'generated_at' => now(),
                    ]
                );
                
            // Always load data without pagination
            $reportData = $this->getInventoryReportData($request, $monthYear);
            
            return inertia('Report/WarehouseMonthlyReport', [
                'reportData' => $reportData,
                'monthYear' => $monthYear,
                'warehouses' => $warehouses,
                'inventoryReport' => $inventoryReport->load([
                    'submittedBy' => function ($query) {
                        $query->select('id', 'name');
                    },
                    'reviewedBy' => function ($query) {
                        $query->select('id', 'name');
                    },
                    'approvedBy' => function ($query) {
                        $query->select('id', 'name');
                    },
                    'rejectedBy' => function ($query) {
                        $query->select('id', 'name');
                    }
                ]),
                'filters' => $request->only(['month_year', 'warehouse_id', 'per_page', 'page']),
            ]);
            
        } catch (\Throwable $th) {
            Log::error('Warehouse Monthly Report Error: ' . $th->getMessage());
            Log::error($th->getTraceAsString());
            return back()->with('error', 'Failed to load report page: ' . $th->getMessage());
        }
    }

    /**
     * Update inventory report adjustments
     */
    public function updateInventoryReportAdjustments(Request $request)
    {
        try {
            $request->validate([
                'month_year' => 'required|string',
                'adjustments' => 'required|array',
                'adjustments.*.product_id' => 'required|exists:products,id',
                'adjustments.*.positive_adjustment' => 'required|numeric|min:0',
                'adjustments.*.negative_adjustment' => 'required|numeric|min:0',
                'adjustments.*.months_of_stock' => 'nullable|string',
            ]);

            return DB::transaction(function () use ($request) {
                $inventoryReport = InventoryReport::where('month_year', $request->month_year)->first();
                
                if (!$inventoryReport) {
                    return response()->json(['message' => 'Inventory report not found for this month.'], 404);
                }

                if (!$inventoryReport->canBeEdited()) {
                    return response()->json(['message' => 'This report cannot be edited in its current status.'], 403);
                }

                foreach ($request->adjustments as $adjustment) {
                    $reportItem = $inventoryReport->items()
                        ->where('product_id', $adjustment['product_id'])
                        ->first();

                    if ($reportItem) {
                        $closingBalance = $reportItem->beginning_balance 
                            + $reportItem->received_quantity 
                            - $reportItem->issued_quantity 
                            + $adjustment['positive_adjustment'] 
                            - $adjustment['negative_adjustment'];
                            
                        $totalCost = $closingBalance * $reportItem->unit_cost;
                        
                        $updateData = [
                            'positive_adjustment' => $adjustment['positive_adjustment'],
                            'negative_adjustment' => $adjustment['negative_adjustment'],
                            // Update closing balance
                            'closing_balance' => $closingBalance,
                        ];
                        
                        // Only update months_of_stock if it's provided in the request
                        if (isset($adjustment['months_of_stock'])) {
                            $updateData['months_of_stock'] = $adjustment['months_of_stock'];
                        }
                        
                        $reportItem->update($updateData);
                    }
                }

                return response()->json(['message' => 'Adjustments updated successfully.'], 200);
            });

        } catch (\Throwable $th) {
            Log::error('Update Inventory Report Adjustments Error: ' . $th->getMessage());
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    /**
     * Submit inventory report for review
     */
    public function submitInventoryReport(Request $request)
    {
        try {
            $request->validate([
                'month_year' => 'required|string',
            ]);

            $inventoryReport = InventoryReport::where('month_year', $request->month_year)->firstOrFail();

            if ($inventoryReport->status !== 'pending') {
                return response()->json(['message' => 'Only pending reports can be submitted.'], 403);
            }

            $inventoryReport->update([
                'status' => 'submitted',
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);

            return response()->json([
                'message' => 'Report submitted successfully.',
                'status' => 'submitted'
            ]);

        } catch (\Throwable $th) {
            Log::error('Submit Report Error: ' . $th->getMessage());
            return response()->json(['message' => 'Failed to submit report.'], 500);
        }
    }

    /**
     * Review inventory report
     */
    public function reviewInventoryReport(Request $request)
    {
        try {
            $request->validate([
                'month_year' => 'required|string',
            ]);

            $inventoryReport = InventoryReport::where('month_year', $request->month_year)->firstOrFail();

            if ($inventoryReport->status !== 'submitted') {
                return response()->json(['message' => 'Only submitted reports can be reviewed.'], 403);
            }

            $inventoryReport->update([
                'status' => 'under_review',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Report marked as under review.',
                'status' => 'under_review'
            ]);

        } catch (\Throwable $th) {
            Log::error('Review Report Error: ' . $th->getMessage());
            return response()->json(['message' => 'Failed to review report.'], 500);
        }
    }

    /**
     * Approve inventory report
     */
    public function approveInventoryReport(Request $request)
    {
        try {
            $request->validate([
                'month_year' => 'required|string',
            ]);

            $inventoryReport = InventoryReport::where('month_year', $request->month_year)->firstOrFail();

            if ($inventoryReport->status !== 'under_review') {
                return response()->json(['message' => 'Only reports under review can be approved.'], 403);
            }

            // Update report status
            $inventoryReport->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Create WarehouseAmc records from issued quantities
            $this->createWarehouseAmcFromReport($inventoryReport);

            return response()->json([
                'message' => 'Report approved successfully and AMC records created.',
                'status' => 'approved'
            ]);

        } catch (\Throwable $th) {
            Log::error('Approve Report Error: ' . $th->getMessage());
            return response()->json(['message' => 'Failed to approve report.'], 500);
        }
    }

    /**
     * Create WarehouseAmc records from approved inventory report
     */
    private function createWarehouseAmcFromReport($inventoryReport)
    {
        try {
            // Get all report items (including those with zero issued quantities)
            $reportItems = $inventoryReport->items()->get();

            foreach ($reportItems as $item) {
                // Create or update WarehouseAmc record (even if quantity is zero)
                WarehouseAmc::updateOrCreate(
                    [
                        'product_id' => $item->product_id,
                        'month_year' => $inventoryReport->month_year,
                    ],
                    [
                        'quantity' => $item->issued_quantity,
                    ]
                );
            }

            Log::info("Created WarehouseAmc records for report {$inventoryReport->month_year} with " . $reportItems->count() . " items");

        } catch (\Throwable $th) {
            Log::error('Create WarehouseAmc Error: ' . $th->getMessage());
            // Don't throw exception here to avoid breaking the approval process
        }
    }

    /**
     * Create MonthlyConsumptionReport records from approved facility LMIS report
     */
    private function createMonthlyConsumptionFromFacilityReport($facilityReport)
    {
        try {
            // Get all report items (including those with zero stock issued)
            $reportItems = $facilityReport->items()->get();

            // Create or get the MonthlyConsumptionReport for this facility and period
            $monthlyConsumptionReport = MonthlyConsumptionReport::updateOrCreate(
                [
                    'facility_id' => $facilityReport->facility_id,
                    'month_year' => $facilityReport->report_period,
                ],
                [
                    'generated_by' => auth()->id(),
                ]
            );

            // Create/Update MonthlyConsumptionItem records
            foreach ($reportItems as $item) {
                MonthlyConsumptionItem::updateOrCreate(
                    [
                        'parent_id' => $monthlyConsumptionReport->id,
                        'product_id' => $item->product_id,
                    ],
                    [
                        'quantity' => (int) $item->stock_issued, // Convert decimal to integer
                    ]
                );
            }

            Log::info("Created MonthlyConsumptionReport for facility {$facilityReport->facility_id} period {$facilityReport->report_period} with " . $reportItems->count() . " items");

        } catch (\Throwable $th) {
            Log::error('Create MonthlyConsumptionReport from Facility Report Error: ' . $th->getMessage());
            // Don't throw exception here to avoid breaking the approval process
        }
    }

    /**
     * Reject inventory report
     */
    public function rejectInventoryReport(Request $request)
    {
        try {
            $request->validate([
                'month_year' => 'required|string',
                'reason' => 'nullable|string|max:500',
            ]);

            $inventoryReport = InventoryReport::where('month_year', $request->month_year)->firstOrFail();

            if ($inventoryReport->status !== 'under_review') {
                return response()->json(['message' => 'Only reports under review can be rejected.'], 403);
            }

            $inventoryReport->update([
                'status' => 'rejected',
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $request->reason,
            ]);

            return response()->json([
                'message' => 'Report rejected successfully.',
                'status' => 'rejected'
            ]);

        } catch (\Throwable $th) {
            Log::error('Reject Report Error: ' . $th->getMessage());
            return response()->json(['message' => 'Failed to reject report.'], 500);
        }
    }

    public function orders(Request $request)
    {
        // Get facilities for dropdown
        $facilities = Facility::get()->pluck('name')->toArray();
    
        $query = Order::query();
    
        // Eager load nested relationships
        $query->with([
            'items.inventory_allocations.back_order',
            'items.inventory_allocations.product:id,name',
            'items.inventory_allocations.warehouse',
            'items.inventory_allocations.location',
            'facility',
            'user',
            'approvedBy',
            'rejectedBy',
            'dispatchedBy'
        ]);
    
        // Filters
        if ($request->filled('facility')) {
            $query->whereHas('facility', function ($q) use ($request) {
                $q->where('name', $request->facility);
            });
        }
    
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
    
        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('order_date', $request->date_from);
        }
    
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('order_date', [$request->date_from, $request->date_to]);
        }
    
        $orders = $query->paginate(
            $request->input('per_page', 25),
            ['*'],
            'page',
            $request->input('page', 1)
        )->withQueryString();
    
        // Transform orders: extract inventory_allocations, remove items
        $orders->getCollection()->transform(function ($order) {
            $inventoryAllocations = collect();
    
            foreach ($order->items as $item) {
                foreach ($item->inventory_allocations as $alloc) {
                    $inventoryAllocations->push($alloc);
                }
            }
    
            // Remove items relation and add top-level inventory_allocations
            $order->unsetRelation('items');
            $order->inventory_allocations = $inventoryAllocations;
    
            return $order;
        });
    
        // Set full path to keep proper pagination links
        $orders->setPath(url()->current());
    
        return inertia('Report/Orders', [
            'orders' => $orders,
            'filters' => $request->only('facility', 'status', 'per_page', 'page', 'date_from', 'date_to'),
            'facilities' => $facilities
        ]);
    }

    public function orderTracking(Request $request)
    {
        // Get facilities for dropdown
        $facilities = Facility::get()->pluck('name')->toArray();
    
        $query = Order::query();
    
        // Eager load order-level relationships and items with full information
        $query->with([
            'facility.handledby',
            'createdBy',
            'approvedBy',
            'rejectedBy',
            'dispatchedBy',
            'items.product:id,name,dosage_id',
            'items.product.dosage:id,name',
            'items.warehouse:id,name',
            'items.inventory_allocations.product:id,name',
            'items.inventory_allocations.warehouse:id,name',
            'items.inventory_allocations.location:id,location'
        ]);
    
        // Filters
        if ($request->filled('facility')) {
            $query->whereHas('facility', function ($q) use ($request) {
                $q->where('name', $request->facility);
            });
        }
    
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
    
        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('order_date', $request->date_from);
        }
    
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('order_date', [$request->date_from, $request->date_to]);
        }
    
        $orders = $query->paginate(
            $request->input('per_page', 25),
            ['*'],
            'page',
            $request->input('page', 1)
        )->withQueryString();
    
        // Transform orders with order-level tracking data
        $orders->getCollection()->transform(function ($order) {
            // Calculate order-level metrics using only OrderItem fields
            $orderStats = DB::table('order_items')
                ->where('order_items.order_id', $order->id)
                ->selectRaw('
                    SUM(order_items.quantity_to_release) as total_allocated,
                    SUM(COALESCE(order_items.received_quantity, 0)) as total_received
                ')
                ->first();

            $totalAllocated = $orderStats->total_allocated ?? 0;
            $totalReceived = $orderStats->total_received ?? 0;
            $fulfillmentPercentage = $totalAllocated > 0 ? round(($totalReceived / $totalAllocated) * 100) : 0;

            $order->tracking_data = [
                'total_allocated' => $totalAllocated,
                'total_received' => $totalReceived,
                'fulfillment_percentage' => $fulfillmentPercentage,
            ];

            return $order;
        });
    
        // Set full path to keep proper pagination links
        $orders->setPath(url()->current());
    
        return inertia('Report/OrderTracking', [
            'orders' => $orders,
            'filters' => $request->only('facility', 'status', 'per_page', 'page', 'date_from', 'date_to'),
            'facilities' => $facilities
        ]);
    }

    public function orderFulfillment(Request $request)
    {
        // Get facilities for dropdown
        $facilities = Facility::get()->pluck('name')->toArray();
    
        $query = Order::query();
    
        // Eager load nested relationships for fulfillment analysis
        $query->with([
            'items.inventory_allocations.back_order',
            'items.inventory_allocations.product:id,name',
            'items.inventory_allocations.warehouse',
            'items.inventory_allocations.location',
            'facility.handledby',
            'user',
            'approvedBy',
            'rejectedBy',
            'dispatchedBy'
        ]);
    
        // Filters
        if ($request->filled('facility')) {
            $query->whereHas('facility', function ($q) use ($request) {
                $q->where('name', $request->facility);
            });
        }
    
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
    
        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('order_date', $request->date_from);
        }
    
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('order_date', [$request->date_from, $request->date_to]);
        }
    
        $orders = $query->paginate(
            $request->input('per_page', 25),
            ['*'],
            'page',
            $request->input('page', 1)
        )->withQueryString();
    
        // Transform orders: extract inventory_allocations, remove items, and add tracking_data
        $orders->getCollection()->transform(function ($order) {
            $inventoryAllocations = collect();
            $totalAllocated = 0;
            $totalReceived = 0;
    
            foreach ($order->items as $item) {
                foreach ($item->inventory_allocations as $alloc) {
                    $inventoryAllocations->push($alloc);
                }
                // Use OrderItem fields directly
                $totalAllocated += $item->quantity_to_release ?? 0;
                $totalReceived += $item->received_quantity ?? 0;
            }
    
            // Calculate fulfillment percentage
            $fulfillmentPercentage = $totalAllocated > 0 ? round(($totalReceived / $totalAllocated) * 100) : 0;
    
            // Remove items relation and add top-level inventory_allocations
            $order->unsetRelation('items');
            $order->inventory_allocations = $inventoryAllocations;
            
            // Add tracking_data with fulfillment metrics
            $order->tracking_data = [
                'total_allocated' => $totalAllocated,
                'total_received' => $totalReceived,
                'fulfillment_percentage' => $fulfillmentPercentage,
            ];
    
            return $order;
        });
    
        // Set full path to keep proper pagination links
        $orders->setPath(url()->current());

        // Calculate fulfillment metrics
        $fulfillmentMetrics = $this->calculateFulfillmentMetrics($query->get());
    
        return inertia('Report/OrderFulfillment', [
            'orders' => $orders,
            'filters' => $request->only('facility', 'status', 'per_page', 'page', 'date_from', 'date_to'),
            'facilities' => $facilities,
            'fulfillmentMetrics' => $fulfillmentMetrics,
        ]);
    }

    private function calculateFulfillmentMetrics($orders)
    {
        $totalOrders = $orders->count();
        $totalItems = 0;
        $totalAllocated = 0;
        $totalReceived = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $totalItems += $item->quantity;
                
                foreach ($item->inventory_allocations as $allocation) {
                    $totalAllocated += $allocation->allocated_inventory ?? 0;
                    $totalReceived += $allocation->received_quantity ?? 0;
                }
            }
        }

        $allocationRate = $totalItems > 0 ? ($totalAllocated / $totalItems) * 100 : 0;
        $fulfillmentRate = $totalItems > 0 ? ($totalReceived / $totalItems) * 100 : 0;
        $efficiencyRate = $totalAllocated > 0 ? ($totalReceived / $totalAllocated) * 100 : 0;

        return [
            'totalOrders' => $totalOrders,
            'totalItems' => $totalItems,
            'totalAllocated' => $totalAllocated,
            'totalReceived' => $totalReceived,
            'allocationRate' => round($allocationRate, 2),
            'fulfillmentRate' => round($fulfillmentRate, 2),
            'efficiencyRate' => round($efficiencyRate, 2),
        ];
    }

    public function transfers(Request $request)
    {
        // Get facilities for dropdown
        $facilities = Facility::get()->pluck('name')->toArray();
        $warehouses = Warehouse::get()->pluck('name')->toArray();

        $query = Transfer::query();
    
        // Eager load nested relationships
        $query->with([
            'items.product',
            'toFacility',
            'fromFacility',
            'toWarehouse',
            'fromWarehouse',
            // 'createdBy',
            'approvedBy',
            'rejectedBy',
            'dispatchedBy'
        ]);
    
        // Filters
        if ($request->filled('facility')) {
            $query->whereHas('toFacility', function ($q) use ($request) {
                $q->where('name', $request->facility);
            })->orWhereHas('fromFacility', function ($q) use ($request) {
                $q->where('name', $request->facility);
            });
        }

        // warehouses
        if ($request->filled('warehouse')) {
            $query->whereHas('toWarehouse', function ($q) use ($request) {
                $q->where('name', $request->warehouse);
            })->orWhereHas('fromWarehouse', function ($q) use ($request) {
                $q->where('name', $request->warehouse);
            });
        }
    
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
    
        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('transfer_date', $request->date_from);
        }
    
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('transfer_date', [$request->date_from, $request->date_to]);
        }
    
        $transfers = $query->paginate(
            $request->input('per_page', 25),
            ['*'],
            'page',
            $request->input('page', 1)
        )
            ->withQueryString();
    
        // Set full path to keep proper pagination links
        $transfers->setPath(url()->current());
    
        return inertia('Report/Transfers', [
            'transfers' => $transfers,
            'filters' => $request->only('facility','warehouse', 'status', 'per_page', 'page', 'date_from', 'date_to'),
            'facilities' => $facilities,
            'warehouses' => $warehouses
        ]);
    }

    /**
     * Transfer Issued Quantity Report
     */
    public function transferIssuedQuantity(Request $request)
    {
        // Get facilities and warehouses for dropdown
        $facilities = Facility::get()->pluck('name')->toArray();
        $warehouses = Warehouse::get()->pluck('name')->toArray();

        $query = IssuedQuantity::query()
            ->with([
                'product.category',
                'product.dosage',
                'transfer.toFacility',
                'transfer.fromFacility',
                'transfer.toWarehouse',
                'transfer.fromWarehouse',
                'transfer.user',
                'issuer',
                'warehouse'
            ]);

        // Apply filters
        if ($request->filled('facility')) {
            $query->whereHas('transfer.fromFacility', function ($q) use ($request) {
                $q->where('name', $request->facility);
            });
        }

        if ($request->filled('warehouse')) {
            $query->whereHas('transfer.fromWarehouse', function ($q) use ($request) {
                $q->where('name', $request->warehouse);
            });
        }

        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('issued_date', $request->date_from);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('issued_date', [$request->date_from, $request->date_to]);
        }

        $issuedQuantities = $query->paginate(
            $request->input('per_page', 25),
            ['*'],
            'page',
            $request->input('page', 1)
        )->withQueryString();

        $issuedQuantities->setPath(url()->current());

        return inertia('Report/TransferIssuedQuantity', [
            'issuedQuantities' => $issuedQuantities,
            'filters' => $request->only('facility', 'warehouse', 'per_page', 'page', 'date_from', 'date_to'),
            'facilities' => $facilities,
            'warehouses' => $warehouses
        ]);
    }

    /**
     * Transfer Received Quantity Report
     */
    public function transferReceivedQuantity(Request $request)
    {
        // Get facilities and warehouses for dropdown
        $facilities = Facility::get()->pluck('name')->toArray();
        $warehouses = Warehouse::get()->pluck('name')->toArray();

        $query = ReceivedQuantity::query()
            ->with([
                'product.category',
                'product.dosage',
                'transfer.toFacility',
                'transfer.fromFacility',
                'transfer.toWarehouse',
                'transfer.fromWarehouse',
                'transfer.user',
                'receiver',
                'warehouse'
            ]);

        // Apply filters
        if ($request->filled('facility')) {
            $query->whereHas('transfer.toFacility', function ($q) use ($request) {
                $q->where('name', $request->facility);
            });
        }

        if ($request->filled('warehouse')) {
            $query->whereHas('warehouse', function ($q) use ($request) {
                $q->where('name', $request->warehouse);
            });
        }

        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('received_at', $request->date_from);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('received_at', [$request->date_from, $request->date_to]);
        }

        $receivedQuantities = $query->paginate(
            $request->input('per_page', 25),
            ['*'],
            'page',
            $request->input('page', 1)
        )->withQueryString();

        $receivedQuantities->setPath(url()->current());

        return inertia('Report/TransferReceivedQuantity', [
            'receivedQuantities' => $receivedQuantities,
            'filters' => $request->only('facility', 'warehouse', 'per_page', 'page', 'date_from', 'date_to'),
            'facilities' => $facilities,
            'warehouses' => $warehouses
        ]);
    }

    /**
     * Transfer Type Report
     */
    public function transferType(Request $request)
    {
        $query = Transfer::query()
            ->with([
                'items.product.category',
                'toFacility',
                'fromFacility',
                'toWarehouse',
                'fromWarehouse'
            ])
            ->where('status', '!=', 'draft');

        // Apply date filters
        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('transfer_date', $request->date_from);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('transfer_date', [$request->date_from, $request->date_to]);
        }

        // Apply transfer type filter
        if ($request->filled('transfer_type')) {
            $query->where(function ($q) use ($request) {
                switch ($request->transfer_type) {
                    case 'Facility to Facility':
                        $q->whereNotNull('from_facility_id')->whereNotNull('to_facility_id');
                        break;
                    case 'Warehouse to Warehouse':
                        $q->whereNotNull('from_warehouse_id')->whereNotNull('to_warehouse_id');
                        break;
                    case 'Warehouse to Facility':
                        $q->whereNotNull('from_warehouse_id')->whereNotNull('to_facility_id');
                        break;
                    case 'Facility to Warehouse':
                        $q->whereNotNull('from_facility_id')->whereNotNull('to_warehouse_id');
                        break;
                    case 'Other':
                        $q->where(function ($subQ) {
                            $subQ->whereNull('from_facility_id')->whereNull('to_facility_id')
                                 ->whereNull('from_warehouse_id')->whereNull('to_warehouse_id');
                        });
                        break;
                }
            });
        }

        // Group by transfer type and get statistics
        $transferTypes = $query->get()->groupBy(function ($transfer) {
            if ($transfer->fromWarehouse && $transfer->toWarehouse) {
                return 'Warehouse to Warehouse';
            } elseif ($transfer->fromFacility && $transfer->toFacility) {
                return 'Facility to Facility';
            } elseif ($transfer->fromWarehouse && $transfer->toFacility) {
                return 'Warehouse to Facility';
            } elseif ($transfer->fromFacility && $transfer->toWarehouse) {
                return 'Facility to Warehouse';
            } else {
                return 'Other';
            }
        })->map(function ($transfers, $type) {
            return [
                'type' => $type,
                'count' => $transfers->count(),
                'total_quantity' => $transfers->sum(function ($transfer) {
                    return $transfer->items->sum('quantity');
                }),
                'total_value' => $transfers->sum(function ($transfer) {
                    return $transfer->items->sum(function ($item) {
                        return $item->quantity * ($item->product->unit_price ?? 0);
                    });
                })
            ];
        });

        return inertia('Report/TransferType', [
            'transferTypes' => $transferTypes,
            'transferReasons' => [], // Empty for type report
            'reasons' => Reason::orderBy('name')->get(),
            'filters' => $request->only('date_from', 'date_to', 'transfer_type', 'reason')
        ]);
    }

    /**
     * Transfer Reasons Report
     */
    public function transferReasons(Request $request)
    {
        $query = Transfer::query()
            ->with([
                'items.product.category',
                'items.inventory_allocations',
                'toFacility',
                'fromFacility',
                'toWarehouse',
                'fromWarehouse'
            ])
            ->where('status', '!=', 'draft');

        // Apply date filters
        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('transfer_date', $request->date_from);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('transfer_date', [$request->date_from, $request->date_to]);
        }

        // Apply transfer type filter
        if ($request->filled('transfer_type')) {
            $query->where(function ($q) use ($request) {
                switch ($request->transfer_type) {
                    case 'Facility to Facility':
                        $q->whereNotNull('from_facility_id')->whereNotNull('to_facility_id');
                        break;
                    case 'Warehouse to Warehouse':
                        $q->whereNotNull('from_warehouse_id')->whereNotNull('to_warehouse_id');
                        break;
                    case 'Warehouse to Facility':
                        $q->whereNotNull('from_warehouse_id')->whereNotNull('to_facility_id');
                        break;
                    case 'Facility to Warehouse':
                        $q->whereNotNull('from_facility_id')->whereNotNull('to_warehouse_id');
                        break;
                    case 'Other':
                        $q->where(function ($subQ) {
                            $subQ->whereNull('from_facility_id')->whereNull('to_facility_id')
                                 ->whereNull('from_warehouse_id')->whereNull('to_warehouse_id');
                        });
                        break;
                }
            });
        }

        // Apply reason filter
        if ($request->filled('reason')) {
            $query->whereHas('items.inventory_allocations', function ($q) use ($request) {
                $q->where('transfer_reason', $request->reason);
            });
        }

        // Get transfers and group by reason from inventory allocations
        $transfers = $query->get();
        
        // Group by reason from inventory allocations
        $transferReasons = collect();
        
        foreach ($transfers as $transfer) {
            foreach ($transfer->items as $item) {
                foreach ($item->inventory_allocations as $allocation) {
                    $reason = $allocation->transfer_reason ?: 'No Reason Specified';
                    
                    if (!$transferReasons->has($reason)) {
                        $transferReasons->put($reason, [
                            'reason' => $reason,
                            'count' => 0,
                            'total_quantity' => 0,
                            'total_value' => 0
                        ]);
                    }
                    
                    $transferReasons->get($reason)['count']++;
                    $transferReasons->get($reason)['total_quantity'] += $allocation->allocated_quantity ?? 0;
                    $transferReasons->get($reason)['total_value'] += ($allocation->allocated_quantity ?? 0) * ($allocation->unit_cost ?? 0);
                }
            }
        }

        // Sort by count descending
        $transferReasons = $transferReasons->sortByDesc('count')->values();

        return inertia('Report/TransferType', [
            'transferTypes' => [], // Empty for reasons report
            'transferReasons' => $transferReasons,
            'reasons' => Reason::orderBy('name')->get(),
            'filters' => $request->only('date_from', 'date_to', 'transfer_type', 'reason')
        ]);
    }


    // purchase orders
    public function purchaseOrders(Request $request)
    {
        $suppliers = Supplier::get()->pluck('name')->toArray();
    
        $purchaseOrdersQuery = PurchaseOrder::query();
    
        if ($request->filled('supplier')) {
            $purchaseOrdersQuery->whereHas('supplier', function($query) use ($request) {
                $query->where('name', $request->supplier);
            });
        }
    
        if ($request->filled('status')) {
            $purchaseOrdersQuery->where('status', $request->status);
        }
    
        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $purchaseOrdersQuery->whereDate('po_date', $request->date_from);
        }
    
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $purchaseOrdersQuery->whereBetween('po_date', [$request->date_from, $request->date_to]);
        }
    
        // ✅ Now assign the result of paginate() to a variable
        $purchaseOrders = $purchaseOrdersQuery
            ->with(['items.product.dosage', 'items.product.category', 'supplier', 'creator', 'approvedBy', 'rejectedBy', 'reviewedBy'])
            ->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();

        $purchaseOrders->setPath(url()->current());

    
        return inertia('Report/PurchaseOrder', [
            'suppliers' => $suppliers,
            'purchaseOrders' => PurchaseOrderResource::collection($purchaseOrders),
            'filters' => $request->only('per_page', 'page', 'supplier', 'date_from', 'date_to', 'status')
        ]);
    }

    // packing list
    public function packingList(Request $request)
    {
        $supplier = Supplier::get()->pluck('name')->toArray();
        $packingLists = PackingList::query();

        if ($request->filled('search')) {
            $packingLists->whereHas('purchaseOrder', function($query) use ($request) {
                $query->where('ref_no', $request->search)
                ->orWhere('po_number', $request->search);
            })
            ->orWhere('ref_no', $request->search)
            ->orWhere('packing_list_number', $request->search);
        }

        if ($request->filled('supplier')) {
            $packingLists->whereHas('purchaseOrder.supplier', function($query) use ($request) {
                $query->where('name', $request->supplier);
            });
        }

        if ($request->filled('status')) {
            $packingLists->where('status', $request->status);
        }

        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $packingLists->whereDate('pk_date', $request->date_from);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $packingLists->whereBetween('pk_date', [$request->date_from, $request->date_to]);
        }

        // ✅ Now assign the result of paginate() to a variable
        $packingLists = $packingLists
            ->with(['items.product.dosage','items.warehouse','items.location', 'items.product.category', 'purchaseOrder.supplier', 'confirmedBy', 'approvedBy', 'rejectedBy', 'reviewedBy'])
            ->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();

        $packingLists->setPath(url()->current());

        return inertia('Report/PackingList', [
            'suppliers' => $supplier,
            'packingLists' => PackingListResource::collection($packingLists),
            'filters' => $request->only('search','per_page', 'page', 'supplier', 'date_from', 'date_to', 'status','purchaser_order')
        ]);
    }

    public function lmisMonthlyReport(Request $request){
        // Group facilities by their district name
        $facilities = Facility::select('id', 'name', 'district')->get()
            ->groupBy('district')
            ->map(function ($group) {
                return $group->values(); // reset array keys
            });

        $report = FacilityMonthlyReport::where('report_period', $request->month_year)
            ->with('items.product.category','facility','submittedBy','reviewedBy','approvedBy','rejectedBy')->first();
    
        return inertia('Report/LMISMonthlyReport', [
            'facilitiesGrouped' => $facilities,
            'report' => $report,
            'filters' => $request->only('facility', 'month_year')
        ]);
    }

    /**
     * Review LMIS Monthly Report
     */
    public function reviewLmisReport(Request $request)
    {
        try {
            $request->validate([
                'report_period' => 'required|string',
                'facility_id' => 'required|integer',
            ]);

            Log::info('Review LMIS Report Request:', $request->all());

            $report = FacilityMonthlyReport::where('report_period', $request->report_period)
                ->where('facility_id', $request->facility_id)
                ->first();

            if (!$report) {
                Log::warning('LMIS Report not found:', [
                    'report_period' => $request->report_period,
                    'facility_id' => $request->facility_id
                ]);
                return response()->json(['message' => 'Report not found for the specified facility and period.'], 404);
            }

            if ($report->status !== 'submitted') {
                Log::warning('LMIS Report wrong status:', [
                    'current_status' => $report->status,
                    'expected_status' => 'submitted'
                ]);
                return response()->json(['message' => "Only submitted reports can be reviewed. Current status: {$report->status}"], 403);
            }

            $report->update([
                'status' => 'reviewed',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            Log::info('LMIS Report reviewed successfully:', ['report_id' => $report->id]);

            return response()->json([
                'message' => 'LMIS report marked as reviewed.',
                'status' => 'reviewed'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Review LMIS Report Validation Error:', $e->errors());
            return response()->json([
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $th) {
            Log::error('Review LMIS Report Error: ' . $th->getMessage(), [
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to review LMIS report: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Approve LMIS Monthly Report
     */
    public function approveLmisReport(Request $request)
    {
        try {
            $request->validate([
                'report_period' => 'required|string',
                'facility_id' => 'required|integer',
            ]);

            Log::info('Approve LMIS Report Request:', $request->all());

            $report = FacilityMonthlyReport::where('report_period', $request->report_period)
                ->where('facility_id', $request->facility_id)
                ->first();

            if (!$report) {
                Log::warning('LMIS Report not found for approval:', [
                    'report_period' => $request->report_period,
                    'facility_id' => $request->facility_id
                ]);
                return response()->json(['message' => 'Report not found for the specified facility and period.'], 404);
            }

            if ($report->status !== 'reviewed') {
                Log::warning('LMIS Report wrong status for approval:', [
                    'current_status' => $report->status,
                    'expected_status' => 'reviewed'
                ]);
                return response()->json(['message' => "Only reviewed reports can be approved. Current status: {$report->status}"], 403);
            }

            $report->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Create/Update MonthlyConsumptionReport records from facility consumption data
            $this->createMonthlyConsumptionFromFacilityReport($report);

            Log::info('LMIS Report approved successfully:', ['report_id' => $report->id]);

            return response()->json([
                'message' => 'LMIS report approved successfully and consumption records updated.',
                'status' => 'approved'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Approve LMIS Report Validation Error:', $e->errors());
            return response()->json([
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $th) {
            Log::error('Approve LMIS Report Error: ' . $th->getMessage(), [
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to approve LMIS report: ' . $th->getMessage()], 500);
        }
    }

    /**
     * Reject LMIS Monthly Report
     */
    public function rejectLmisReport(Request $request)
    {
        try {
            $request->validate([
                'report_period' => 'required|string',
                'facility_id' => 'required|integer',
                'reason' => 'nullable|string|max:500',
            ]);

            Log::info('Reject LMIS Report Request:', $request->all());

            $report = FacilityMonthlyReport::where('report_period', $request->report_period)
                ->where('facility_id', $request->facility_id)
                ->first();

            if (!$report) {
                Log::warning('LMIS Report not found for rejection:', [
                    'report_period' => $request->report_period,
                    'facility_id' => $request->facility_id
                ]);
                return response()->json(['message' => 'Report not found for the specified facility and period.'], 404);
            }

            if ($report->status !== 'reviewed') {
                Log::warning('LMIS Report wrong status for rejection:', [
                    'current_status' => $report->status,
                    'expected_status' => 'reviewed'
                ]);
                return response()->json(['message' => "Only reviewed reports can be rejected. Current status: {$report->status}"], 403);
            }

            $report->update([
                'status' => 'rejected',
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'comments' => $request->reason, // Using comments field for rejection reason
            ]);

            Log::info('LMIS Report rejected successfully:', ['report_id' => $report->id]);

            return response()->json([
                'message' => 'LMIS report rejected successfully.',
                'status' => 'rejected'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Reject LMIS Report Validation Error:', $e->errors());
            return response()->json([
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $th) {
            Log::error('Reject LMIS Report Error: ' . $th->getMessage(), [
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to reject LMIS report: ' . $th->getMessage()], 500);
        }
    }

    public function export($monthYear, Request $request)
    {
        $format = $request->input('format', 'excel');
        $report = InventoryReport::where('month_year', $monthYear)->firstOrFail();
        $report->load([
            'items.inventory_allocations.product.category',
        ]);

        if ($format === 'pdf') {
            return PDF::download(
                new OrderReportPdf($report),
                'orders_' . $monthYear . '.pdf'
            );
        }

        return Excel::download(
            new OrderReportExport($report),
            'orders_' . $monthYear . '.xlsx'
        );
    }

    /**
     * Export orders to Excel
     */
    public function exportOrdersToExcel(Request $request)
    {
        $filters = $request->validate([
            'month_year' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'status' => ['nullable', 'string', 'in:pending,approved,rejected,delivered,cancelled'],
        ]);

        return Excel::download(new OrderExport($filters), 'orders_' . $filters['month_year'] . '.xlsx');
    }

    /**
     * Export warehouse monthly report to Excel
     */
    public function exportToExcel(Request $request)
    {
        try {
            $monthYear = $request->input('month_year');
            
            if (!$monthYear) {
                return back()->with('error', 'Month/Year is required for export');
            }
            
            $reportData = $this->getInventoryReportData($request, $monthYear);
            
            $filename = "warehouse_monthly_report_{$monthYear}.xlsx";
            
            return Excel::download(new WarehouseMonthlyReportExport($reportData, $monthYear), $filename);
            
        } catch (\Throwable $th) {
            Log::error('Export Error: ' . $th->getMessage());
            return back()->with('error', 'Failed to export report: ' . $th->getMessage());
        }
    }

    public function exportOrderTrackingExcel(Request $request)
    {
        $filters = $request->only(['facility', 'status', 'date_from', 'date_to', 'per_page', 'page']);
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\OrderTrackingExport($filters), 'order_tracking_report.xlsx');
    }

    /**
     * Active & Inactive Product Report
     */
    public function activeInactiveProducts(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'dosage', 'subCategory']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Filter by category
        if ($request->filled('category_ids') && is_array($request->category_ids)) {
            $categoryIds = collect($request->category_ids)->pluck('id')->filter();
            if ($categoryIds->isNotEmpty()) {
                $query->whereIn('category_id', $categoryIds);
            }
        }

        // Filter by dosage
        if ($request->filled('dosage_ids') && is_array($request->dosage_ids)) {
            $dosageIds = collect($request->dosage_ids)->pluck('id')->filter();
            if ($dosageIds->isNotEmpty()) {
                $query->whereIn('dosage_id', $dosageIds);
            }
        }

        // Search by product name or ID
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('productID', 'like', '%' . $request->search . '%');
            });
        }

        // Get counts for summary cards (before pagination)
        $activeCount = (clone $query)->where('is_active', true)->count();
        $inactiveCount = (clone $query)->where('is_active', false)->count();
        $totalCount = (clone $query)->count();

        $products = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $products->setPath(url()->current()); // Force Laravel to use full URLs

        return inertia('Report/Products/ActiveInactive', [
            'products' => $products,
            'categories' => \App\Models\Category::where('is_active', true)->get(),
            'dosages' => \App\Models\Dosage::where('is_active', true)->get(),
            'filters' => $request->only(['status', 'category_ids', 'dosage_ids', 'search', 'per_page']),
            'summary' => [
                'active_count' => $activeCount,
                'inactive_count' => $inactiveCount,
                'total_count' => $totalCount
            ]
        ]);
    }

    /**
     * Product Eligibility Report
     */
    public function productEligibility(Request $request)
    {
        $query = \App\Models\EligibleItem::query()
            ->with(['product.category', 'product.dosage']);

        // Filter by facility type
        if ($request->filled('facility_type')) {
            if (is_array($request->facility_type)) {
                $facilityTypes = collect($request->facility_type)->pluck('value')->filter();
                if ($facilityTypes->isNotEmpty()) {
                    $query->whereIn('facility_type', $facilityTypes);
                }
            } else {
                $query->where('facility_type', $request->facility_type);
            }
        }

        // Filter by product
        if ($request->filled('product_id')) {
            if (is_array($request->product_id)) {
                $productIds = collect($request->product_id)->pluck('id')->filter();
                if ($productIds->isNotEmpty()) {
                    $query->whereIn('product_id', $productIds);
                }
            } else {
                $query->where('product_id', $request->product_id);
            }
        }

        // Filter by category
        if ($request->filled('category_id')) {
            if (is_array($request->category_id)) {
                $categoryIds = collect($request->category_id)->pluck('id')->filter();
                if ($categoryIds->isNotEmpty()) {
                    $query->whereHas('product', function($q) use ($categoryIds) {
                        $q->whereIn('category_id', $categoryIds);
                    });
                }
            } else {
                $query->whereHas('product', function($q) use ($request) {
                    $q->where('category_id', $request->category_id);
                });
            }
        }

        // Search by product name or ID
        if ($request->filled('search')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('productID', 'like', '%' . $request->search . '%');
            });
        }

        // Get counts for summary cards (before pagination)
        $totalCount = (clone $query)->count();
        $uniqueProductsCount = (clone $query)->distinct('product_id')->count();
        $facilityTypesCount = (clone $query)->distinct('facility_type')->count();

        $eligibleItems = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $eligibleItems->setPath(url()->current()); // Force Laravel to use full URLs

        // Get unique facility types for filter
        $facilityTypes = \App\Models\EligibleItem::distinct('facility_type')
            ->pluck('facility_type')
            ->map(function($type) {
                return [
                    'value' => $type,
                    'label' => $type
                ];
            });

        return inertia('Report/Products/Eligibility', [
            'eligibleItems' => $eligibleItems,
            'products' => \App\Models\Product::where('is_active', true)->get(),
            'categories' => \App\Models\Category::where('is_active', true)->get(),
            'facilityTypes' => $facilityTypes,
            'filters' => $request->only(['facility_type', 'product_id', 'category_id', 'search', 'per_page']),
            'summary' => [
                'total_count' => $totalCount,
                'unique_products_count' => $uniqueProductsCount,
                'facility_types_count' => $facilityTypesCount
            ]
        ]);
    }

    /**
     * Product Category Report
     */
    public function productCategories(Request $request)
    {
        $query = \App\Models\Category::query()
            ->withCount(['products as total_products'])
            ->withCount(['products as active_products' => function($query) {
                $query->where('is_active', true);
            }])
            ->withCount(['products as inactive_products' => function($query) {
                $query->where('is_active', false);
            }]);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Search by category name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Summary counts before pagination
        $totalCount = (clone $query)->count();
        $activeCount = (clone $query)->where('is_active', true)->count();
        $inactiveCount = (clone $query)->where('is_active', false)->count();
        $totalProducts = (clone $query)->get()->sum('total_products');

        $categories = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $categories->setPath(url()->current()); // Force Laravel to use full URLs

        // Get products for each category
        $categories->getCollection()->transform(function($category) {
            $category->products = $category->products()
                ->with(['dosage', 'subCategory'])
                ->get();
            return $category;
        });

        return inertia('Report/Products/Categories', [
            'categories' => $categories,
            'filters' => $request->only(['status', 'search', 'per_page']),
            'summary' => [
                'total_count' => $totalCount,
                'active_count' => $activeCount,
                'inactive_count' => $inactiveCount,
                'total_products' => $totalProducts,
            ]
        ]);
    }

    /**
     * Product Dosage Forms Report
     */
    public function productDosageForms(Request $request)
    {
        $query = \App\Models\Dosage::query()
            ->withCount(['products as total_products'])
            ->withCount(['products as active_products' => function($query) {
                $query->where('is_active', true);
            }])
            ->withCount(['products as inactive_products' => function($query) {
                $query->where('is_active', false);
            }]);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Search by dosage name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $dosages = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $dosages->setPath(url()->current()); // Force Laravel to use full URLs

        // Get products for each dosage
        $dosages->getCollection()->transform(function($dosage) {
            $dosage->products = $dosage->products()
                ->with(['category', 'subCategory'])
                ->get();
            return $dosage;
        });

        return inertia('Report/Products/DosageForms', [
            'dosages' => $dosages,
            'filters' => $request->only(['status', 'search', 'per_page'])
        ]);
    }

    /**
     * Product Expiry Tracking Report
     */
    public function productExpiryTracking(Request $request)
    {
        $now = \Carbon\Carbon::now();
        $sixMonthsFromNow = $now->copy()->addMonths(6);
        $oneYearFromNow = $now->copy()->addYear();

        // Start with InventoryItem as the base model for expiry tracking
        $query = \App\Models\InventoryItem::query()
            ->with(['product.category', 'product.dosage', 'warehouse'])
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date');

        // Filter by expiry timeframe
        if ($request->filled('expiry_timeframe')) {
            $timeframe = $request->expiry_timeframe;
            
            switch ($timeframe) {
                case 'expired':
                    $query->where('expiry_date', '<', $now);
                    break;
                case '6_months':
                    $query->where('expiry_date', '>=', $now)
                          ->where('expiry_date', '<=', $sixMonthsFromNow);
                    break;
                case '1_year':
                    $query->where('expiry_date', '>=', $now)
                          ->where('expiry_date', '<=', $oneYearFromNow);
                    break;
                case 'all_expiring':
                    $query->where('expiry_date', '<=', $oneYearFromNow);
                    break;
            }
        }

        // Filter by product
        if ($request->filled('product_ids') && is_array($request->product_ids)) {
            $productIds = collect($request->product_ids)->pluck('id')->filter();
            if ($productIds->isNotEmpty()) {
                $query->whereIn('product_id', $productIds);
            }
        }

        // Filter by category
        if ($request->filled('category_ids') && is_array($request->category_ids)) {
            $categoryIds = collect($request->category_ids)->pluck('id')->filter();
            if ($categoryIds->isNotEmpty()) {
                $query->whereHas('product', function($q) use ($categoryIds) {
                    $q->whereIn('category_id', $categoryIds);
                });
            }
        }

        // Filter by dosage
        if ($request->filled('dosage_ids') && is_array($request->dosage_ids)) {
            $dosageIds = collect($request->dosage_ids)->pluck('id')->filter();
            if ($dosageIds->isNotEmpty()) {
                $query->whereHas('product', function($q) use ($dosageIds) {
                    $q->whereIn('dosage_id', $dosageIds);
                });
            }
        }

        // Filter by warehouse
        if ($request->filled('warehouse_ids') && is_array($request->warehouse_ids)) {
            $warehouseIds = collect($request->warehouse_ids)->pluck('id')->filter();
            if ($warehouseIds->isNotEmpty()) {
                $query->whereIn('warehouse_id', $warehouseIds);
            }
        }

        // Search by product name, ID, batch number, or barcode
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('batch_number', 'like', '%' . $search . '%')
                  ->orWhere('barcode', 'like', '%' . $search . '%')
                  ->orWhereHas('product', function($prodQ) use ($search) {
                      $prodQ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('productID', 'like', '%' . $search . '%');
                  });
            });
        }

        // Calculate summary counts before pagination
        $baseQuery = clone $query;
        $totalItems = $baseQuery->count();
        $expiredCount = (clone $baseQuery)->where('expiry_date', '<', $now)->count();
        $expiring6MonthsCount = (clone $baseQuery)->where('expiry_date', '>=', $now)
                                                  ->where('expiry_date', '<=', $sixMonthsFromNow)->count();
        $expiring1YearCount = (clone $baseQuery)->where('expiry_date', '>=', $now)
                                                ->where('expiry_date', '<=', $oneYearFromNow)->count();
        $totalQuantity = $baseQuery->sum('quantity');
        $totalValue = $baseQuery->sum(DB::raw('quantity * unit_cost'));

        $expiryItems = $query->orderBy('expiry_date', 'asc')
            ->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $expiryItems->setPath(url()->current());

        // Transform the data to add expiry-related calculations
        $expiryItems->getCollection()->transform(function($item) use ($now) {
            $item->expiry_date = \Carbon\Carbon::parse($item->expiry_date);
            $item->is_expired = $item->expiry_date->lt($now);
            $item->days_until_expiry = $now->diffInDays($item->expiry_date, false);
            $item->expiry_status = $this->getExpiryStatus($item->expiry_date, $now);
            $item->total_value = $item->quantity * ($item->unit_cost ?? 0);
            return $item;
        });

        return inertia('Report/Products/ExpiryTracking', [
            'expiryItems' => $expiryItems,
            'products' => \App\Models\Product::where('is_active', true)->get(),
            'categories' => \App\Models\Category::where('is_active', true)->get(),
            'dosages' => \App\Models\Dosage::where('is_active', true)->get(),
            'warehouses' => \App\Models\Warehouse::all(),
            'filters' => $request->only(['expiry_timeframe', 'product_ids', 'category_ids', 'dosage_ids', 'warehouse_ids', 'search', 'per_page']),
            'summary' => [
                'total_items' => $totalItems,
                'expired_count' => $expiredCount,
                'expiring_6_months_count' => $expiring6MonthsCount,
                'expiring_1_year_count' => $expiring1YearCount,
                'total_quantity' => $totalQuantity,
                'total_value' => $totalValue
            ]
        ]);
    }

    /**
     * Helper method to determine expiry status
     */
    private function getExpiryStatus($expiryDate, $now)
    {
        if ($expiryDate->lt($now)) {
            return 'expired';
        }
        
        $daysUntilExpiry = $now->diffInDays($expiryDate, false);
        
        if ($daysUntilExpiry <= 30) {
            return 'critical';
        } elseif ($daysUntilExpiry <= 90) {
            return 'warning';
        } elseif ($daysUntilExpiry <= 180) {
            return 'notice';
        } else {
            return 'safe';
        }
    }

    /**
     * Liquidation Report
     */
    public function liquidationReport(Request $request)
    {
        $query = \App\Models\Liquidate::query()
            ->with(['items.product.category', 'items.product.dosage', 'liquidatedBy', 'approvedBy', 'rejectedBy', 'reviewedBy']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by source
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Filter by facility
        if ($request->filled('facility')) {
            $query->where('facility', 'like', '%' . $request->facility . '%');
        }

        // Filter by warehouse
        if ($request->filled('warehouse')) {
            $query->where('warehouse', 'like', '%' . $request->warehouse . '%');
        }

        // Filter by date range
        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('liquidated_at', $request->date_from);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('liquidated_at', [$request->date_from, $request->date_to]);
        }

        // Search by liquidation ID
        if ($request->filled('search')) {
            $query->where('liquidate_id', 'like', '%' . $request->search . '%');
        }

        // Calculate summary counts before pagination
        $baseQuery = clone $query;
        $totalLiquidations = $baseQuery->count();
        $approvedCount = (clone $baseQuery)->where('status', 'approved')->count();
        $rejectedCount = (clone $baseQuery)->where('status', 'rejected')->count();
        $pendingCount = (clone $baseQuery)->where('status', 'pending')->count();
        $totalValue = $baseQuery->join('liquidate_items', 'liquidates.id', '=', 'liquidate_items.liquidate_id')
            ->sum('liquidate_items.total_cost');

        $liquidations = $query->orderBy('liquidated_at', 'desc')
            ->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $liquidations->setPath(url()->current());

        // Get unique sources for filter
        $sources = \App\Models\Liquidate::distinct('source')
            ->whereNotNull('source')
            ->pluck('source');

        return inertia('Report/LiquidationDisposal/Liquidation', [
            'liquidations' => $liquidations,
            'filters' => $request->only(['status', 'source', 'facility', 'warehouse', 'date_from', 'date_to', 'search', 'per_page']),
            'summary' => [
                'total_liquidations' => $totalLiquidations,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'pending_count' => $pendingCount,
                'total_value' => $totalValue
            ],
            'sources' => $sources
        ]);
    }

    /**
     * Disposal Report
     */
    public function disposalReport(Request $request)
    {
        $query = \App\Models\Disposal::query()
            ->with(['items.product.category', 'items.product.dosage', 'disposedBy', 'approvedBy', 'rejectedBy', 'reviewedBy']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by source
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Filter by date range
        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('disposed_at', $request->date_from);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('disposed_at', [$request->date_from, $request->date_to]);
        }

        // Search by disposal ID
        if ($request->filled('search')) {
            $query->where('disposal_id', 'like', '%' . $request->search . '%');
        }

        // Calculate summary counts before pagination
        $baseQuery = clone $query;
        $totalDisposals = $baseQuery->count();
        $approvedCount = (clone $baseQuery)->where('status', 'approved')->count();
        $rejectedCount = (clone $baseQuery)->where('status', 'rejected')->count();
        $pendingCount = (clone $baseQuery)->where('status', 'pending')->count();
        $totalValue = $baseQuery->join('disposal_items', 'disposals.id', '=', 'disposal_items.disposal_id')
            ->sum('disposal_items.total_cost');

        $disposals = $query->orderBy('disposed_at', 'desc')
            ->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $disposals->setPath(url()->current());

        // Get unique sources for filter
        $sources = \App\Models\Disposal::distinct('source')
            ->whereNotNull('source')
            ->pluck('source');

        return inertia('Report/LiquidationDisposal/Disposal', [
            'disposals' => $disposals,
            'filters' => $request->only(['status', 'source', 'date_from', 'date_to', 'search', 'per_page']),
            'summary' => [
                'total_disposals' => $totalDisposals,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'pending_count' => $pendingCount,
                'total_value' => $totalValue
            ],
            'sources' => $sources
        ]);
    }

    /**
     * Purchase Orders Report
     */
    public function purchaseOrdersReport(Request $request)
    {
        $query = \App\Models\PurchaseOrder::query()
            ->with(['supplier', 'items.product.category', 'items.product.dosage', 'approvedBy', 'rejectedBy', 'reviewedBy', 'creator']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by date range
        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('po_date', $request->date_from);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('po_date', [$request->date_from, $request->date_to]);
        }

        // Search by PO number
        if ($request->filled('search')) {
            $query->where('po_number', 'like', '%' . $request->search . '%');
        }

        // Calculate summary counts before pagination
        $baseQuery = clone $query;
        $totalPOs = $baseQuery->count();
        $approvedCount = (clone $baseQuery)->where('status', 'approved')->count();
        $rejectedCount = (clone $baseQuery)->where('status', 'rejected')->count();
        $pendingCount = (clone $baseQuery)->where('status', 'pending')->count();
        $totalValue = $baseQuery->sum('total_amount');

        $purchaseOrders = $query->orderBy('po_date', 'desc')
            ->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $purchaseOrders->setPath(url()->current());

        // Get suppliers for filter
        $suppliers = \App\Models\Supplier::orderBy('name')->get(['id', 'name']);

        return inertia('Report/Procurement/PurchaseOrders', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => $request->only(['status', 'supplier_id', 'date_from', 'date_to', 'search', 'per_page']),
            'summary' => [
                'total_pos' => $totalPOs,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'pending_count' => $pendingCount,
                'total_value' => $totalValue
            ],
            'suppliers' => $suppliers
        ]);
    }

    /**
     * Packing List Report
     */
    public function packingListReport(Request $request)
    {
        $query = \App\Models\PackingList::query()
            ->with(['purchaseOrder.supplier', 'items.product.category', 'items.product.dosage', 'confirmedBy', 'reviewedBy', 'approvedBy']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by supplier
        if ($request->filled('supplier_ids') && is_array($request->supplier_ids) && count($request->supplier_ids) > 0) {
            $query->whereHas('purchaseOrder', function ($q) use ($request) {
                $q->whereIn('supplier_id', $request->supplier_ids);
            });
        } else if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by date range
        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('pk_date', $request->date_from);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('pk_date', [$request->date_from, $request->date_to]);
        }

        // Search by packing list number, ref_no, and PO ref_no
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('packing_list_number', 'like', '%' . $searchTerm . '%')
                  ->orWhere('ref_no', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('purchaseOrder', function($poQuery) use ($searchTerm) {
                      $poQuery->where('po_number', 'like', '%' . $searchTerm . '%')
                             ->orWhere('ref_no', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        // Calculate summary counts before pagination
        $baseQuery = clone $query;
        $totalPackingLists = $baseQuery->count();
        $confirmedCount = (clone $baseQuery)->where('status', 'confirmed')->count();
        $pendingCount = (clone $baseQuery)->where('status', 'pending')->count();
        $totalItems = $baseQuery->withCount('items')->get()->sum('items_count');
        $totalValue = $baseQuery->join('packing_list_items', 'packing_lists.id', '=', 'packing_list_items.packing_list_id')
            ->sum('packing_list_items.total_cost');

        $packingLists = $query->orderBy('pk_date', 'desc')
            ->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $packingLists->setPath(url()->current());

        // Get suppliers for filter
        $suppliers = \App\Models\Supplier::orderBy('name')->get(['id', 'name']);

        return inertia('Report/Procurement/PackingList', [
            'packingLists' => $packingLists,
            'filters' => $request->only(['status', 'supplier_ids', 'date_from', 'date_to', 'search', 'per_page']),
            'summary' => [
                'total_packing_lists' => $totalPackingLists,
                'confirmed_count' => $confirmedCount,
                'pending_count' => $pendingCount,
                'total_items' => $totalItems,
                'total_value' => $totalValue
            ],
            'suppliers' => $suppliers
        ]);
    }

    /**
     * Backorder Report
     */
    public function backorderReport(Request $request)
    {        
        $query = \App\Models\BackOrder::query()
            ->with(['packingList.purchaseOrder.supplier', 'differences.product.category', 'differences.product.dosage', 'creator', 'order', 'transfer']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by source type
        if ($request->filled('source_type')) {
            $query->where('source_type', $request->source_type);
        }

        // Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->whereHas('packingList.purchaseOrder', function ($q) use ($request) {
                $q->where('supplier_id', $request->supplier_id);
            });
        }

        // Filter by date range
        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('back_order_date', $request->date_from);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('back_order_date', [$request->date_from, $request->date_to]);
        }

        // Search by back order number
        if ($request->filled('search')) {
            $query->where('back_order_number', 'like', '%' . $request->search . '%');
        }

        // Debug: Log the SQL query
        // Calculate summary counts before pagination
        $baseQuery = clone $query;
        $totalBackOrders = $baseQuery->count();
        $openCount = (clone $baseQuery)->where('status', 'open')->count();
        $closedCount = (clone $baseQuery)->where('status', 'closed')->count();
        $totalItems = $baseQuery->sum('total_items');
        $totalQuantity = $baseQuery->sum('total_quantity');

        $backOrders = $query->orderBy('back_order_date', 'desc')
            ->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $backOrders->setPath(url()->current());

        // Debug: Log the results
        // Get suppliers for filter
        $suppliers = \App\Models\Supplier::orderBy('name')->get(['id', 'name']);

        return inertia('Report/Procurement/Backorder', [
            'backOrders' => $backOrders,
            'filters' => $request->only(['status', 'source_type', 'supplier_id', 'date_from', 'date_to', 'search', 'per_page']),
            'summary' => [
                'total_back_orders' => $totalBackOrders,
                'open_count' => $openCount,
                'closed_count' => $closedCount,
                'total_items' => $totalItems,
                'total_quantity' => $totalQuantity
            ],
            'suppliers' => $suppliers
        ]);
    }

    /**
     * Lead Time Analysis Report
     */
    public function leadTimeAnalysisReport(Request $request)
    {
        $query = \App\Models\PurchaseOrder::query()
            ->with(['supplier', 'packingLists', 'items.product.category']);

        // Filter by supplier
        if ($request->filled('supplier_ids') && is_array($request->supplier_ids) && count($request->supplier_ids) > 0) {
            $query->whereIn('supplier_id', $request->supplier_ids);
        } else if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by date range
        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $query->whereDate('po_date', $request->date_from);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('po_date', [$request->date_from, $request->date_to]);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Calculate lead time metrics
        $purchaseOrders = $query->orderBy('po_date', 'desc')
            ->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $purchaseOrders->setPath(url()->current());



        // Calculate summary statistics
        $baseQuery = clone $query;
        $totalPOs = $baseQuery->count();
        $avgLeadTime = $baseQuery->whereHas('packingLists')
            ->get()
            ->avg(function ($po) {
                if (!$po->po_date || !$po->packingLists || $po->packingLists->isEmpty()) {
                    return null;
                }
                
                // Get the earliest packing list date for this PO
                $earliestPkDate = $po->packingLists->min('pk_date');
                if (!$earliestPkDate) {
                    return null;
                }
                
                // Ensure both dates are Carbon instances
                $poDate = $po->po_date instanceof \Carbon\Carbon ? $po->po_date : \Carbon\Carbon::parse($po->po_date);
                $pkDate = $earliestPkDate instanceof \Carbon\Carbon ? $earliestPkDate : \Carbon\Carbon::parse($earliestPkDate);
                
                return $poDate->diffInDays($pkDate);
            });

        $suppliers = \App\Models\Supplier::orderBy('name')->get(['id', 'name']);



        return inertia('Report/Procurement/LeadTimeAnalysis', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => $request->only(['supplier_ids', 'supplier_id', 'date_from', 'date_to', 'status', 'per_page']),
            'summary' => [
                'total_pos' => $totalPOs,
                'avg_lead_time' => round($avgLeadTime ?? 0, 1)
            ],
            'suppliers' => $suppliers
        ]);
    }

    /**
     * Demand Forecasting Report
     */
    public function demandForecastingReport(Request $request)
    {
        // Date range: last 6 months by default
        $months = (int)($request->input('months', 6));
        $end = now()->startOfMonth();
        $start = (clone $end)->subMonths($months - 1);

        // Get all products
        $products = \App\Models\Product::orderBy('name')->get();

        // Aggregate issued quantities by product and month
        $issued = \App\Models\IssuedQuantity::whereBetween('issued_date', [$start, $end->copy()->endOfMonth()])
            ->get()
            ->groupBy('product_id');

        // Get current stock from InventoryReportItem (latest month)
        $latestInventory = \App\Models\InventoryReport::orderByDesc('month_year')->first();
        $inventoryItems = $latestInventory
            ? $latestInventory->items->keyBy('product_id')
            : collect();

        $forecast = [];
        foreach ($products as $product) {
            $productIssued = $issued->get($product->id, collect());
            // Group by month
            $monthly = $productIssued->groupBy(function($item) {
                return \Carbon\Carbon::parse($item->issued_date)->format('Y-m');
            });
            $monthlySums = $monthly->map(function($items) {
                return $items->sum('quantity');
            });
            $avgMonthly = $monthlySums->count() ? round($monthlySums->avg(), 2) : 0;
            $lastMonthKey = $end->copy()->subMonth()->format('Y-m');
            $lastMonth = $monthlySums->get($lastMonthKey, 0);
            $currentStock = $inventoryItems[$product->id]->closing_balance ?? 0;
            $predicted = $avgMonthly;
            $suggestedReorder = max(0, $predicted - $currentStock);
            $forecast[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'category' => $product->category->name ?? null,
                'avg_monthly_demand' => $avgMonthly,
                'last_month_demand' => $lastMonth,
                'current_stock' => $currentStock,
                'predicted_demand' => $predicted,
                'suggested_reorder' => $suggestedReorder,
            ];
        }

        return inertia('Report/Procurement/DemandForecasting', [
            'forecast' => $forecast,
            'months' => $months,
            'filters' => $request->only(['months']),
        ]);
    }

    /**
     * Facilities List Report
     */
    public function facilitiesListReport(Request $request)
    {
        $query = \App\Models\Facility::with(['user', 'handledby']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('facility_type', 'like', "%{$search}%")
                  ->orWhere('district', 'like', "%{$search}%")
                  ->orWhere('region', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('facility_type')) {
            $facilityTypes = is_array($request->facility_type) ? $request->facility_type : [$request->facility_type];
            $facilityTypes = array_filter($facilityTypes, function($type) { return $type !== ''; });
            if (!empty($facilityTypes)) {
                $query->whereIn('facility_type', $facilityTypes);
            }
        }

        if ($request->filled('district')) {
            $districts = is_array($request->district) ? $request->district : [$request->district];
            $districts = array_filter($districts, function($dist) { return $dist !== ''; });
            if (!empty($districts)) {
                $query->whereIn('district', $districts);
            }
        }

        if ($request->filled('status')) {
            if ($request->status === 'Active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'Inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Get total count before pagination
        $totalCount = $query->count();

        // Apply default sorting by name
        $query->orderBy('name', 'asc');

        // Paginate results
        $perPage = $request->input('per_page', 25);
        $facilities = $query->paginate($perPage, ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $facilities->setPath(url()->current()); // Force Laravel to use full URLs

        // Transform data
        $facilities->getCollection()->transform(function($facility) {
            return [
                'id' => $facility->id,
                'name' => $facility->name,
                'email' => $facility->email ?? '—',
                'type' => $facility->facility_type ?? '—',
                'district' => $facility->district ?? '—',
                'region' => $facility->region ?? '—',
                'phone' => $facility->phone ?? '—',
                'address' => $facility->address ?? '—',
                'status' => $facility->is_active ? 'Active' : 'Inactive',
                'has_cold_storage' => $facility->has_cold_storage ? 'Yes' : 'No',
                'user' => $facility->user ? $facility->user->name : '—',
                'handled_by' => $facility->handledby ? $facility->handledby->name : '—',
                'created_at' => $facility->created_at?->format('Y-m-d'),
            ];
        });

        // Get filter options
        $facilityTypes = \App\Models\Facility::distinct()->pluck('facility_type')->filter()->sort()->values();
        $districts = \App\Models\Facility::distinct()->pluck('district')->filter()->sort()->values();
        $statuses = collect(['Active', 'Inactive']);

        // Calculate summary statistics
        $summary = [
            'total_facilities' => $totalCount,
            'by_type' => \App\Models\Facility::selectRaw('facility_type, COUNT(*) as count')
                ->groupBy('facility_type')
                ->pluck('count', 'facility_type')
                ->toArray(),
            'by_district' => \App\Models\Facility::selectRaw('district, COUNT(*) as count')
                ->groupBy('district')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->pluck('count', 'district')
                ->toArray(),
            'by_status' => [
                'Active' => \App\Models\Facility::where('is_active', true)->count(),
                'Inactive' => \App\Models\Facility::where('is_active', false)->count(),
            ],
        ];

        // Handle Excel export
        if ($request->has('export') && $request->export === 'excel') {
            return $this->exportFacilitiesToExcel($query, $request);
        }

        return inertia('Report/Facilities/FacilitiesList', [
            'facilities' => $facilities,
            'filters' => $request->only(['search', 'facility_type', 'district', 'status', 'date_from', 'date_to', 'per_page','page']),
            'filterOptions' => [
                'facility_types' => $facilityTypes,
                'districts' => $districts,
                'statuses' => $statuses,
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * LMIS Monthly Consumption Report
     */
    public function lmisMonthlyConsumptionReport(Request $request)
    {
        $month = $request->input('month');
        $facilityId = $request->input('facility_id');
        $query = \App\Models\FacilityMonthlyReport::with(['facility', 'items.product']);
        if ($month) {
            $query->where('report_period', $month);
        }
        if ($facilityId) {
            $query->where('facility_id', $facilityId);
        }
        $reports = $query->orderByDesc('report_period')->limit(100)->get();
        $rows = [];
        foreach ($reports as $report) {
            foreach ($report->items as $item) {
                $rows[] = [
                    'facility' => $report->facility->name ?? '—',
                    'month' => $report->report_period,
                    'product' => $item->product->name ?? '—',
                    'stock_received' => $item->stock_received,
                    'stock_issued' => $item->stock_issued,
                    'closing_balance' => $item->closing_balance,
                    'stockout_days' => $item->stockout_days,
                ];
            }
        }
        return inertia('Report/Facilities/LmisMonthlyConsumption', [
            'rows' => $rows,
            'filters' => $request->only(['month', 'facility_id']),
        ]);
    }

    /**
     * Facility Compliance Report
     */
    public function facilityComplianceReport(Request $request)
    {
        $month = $request->input('month');
        $query = \App\Models\FacilityMonthlyReport::with('facility');
        if ($month) {
            $query->where('report_period', $month);
        }
        $reports = $query->orderByDesc('report_period')->limit(100)->get();
        $rows = [];
        foreach ($reports as $report) {
            $rows[] = [
                'facility' => $report->facility->name ?? '—',
                'month' => $report->report_period,
                'status' => $report->status,
                'submitted_at' => $report->submitted_at ? $report->submitted_at->format('Y-m-d') : '—',
                'compliance_rate' => '100%', // Placeholder, can be calculated
            ];
        }
        return inertia('Report/Facilities/FacilityCompliance', [
            'rows' => $rows,
            'filters' => $request->only(['month']),
        ]);
    }

    /**
     * Export Facilities to Excel
     */
    private function exportFacilitiesToExcel($query, $request)
    {
        $facilities = $query->get();
        
        $filename = 'facilities_report_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        
        return \Maatwebsite\Excel\Facades\Excel::download(new class($facilities) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithMapping, \Maatwebsite\Excel\Concerns\WithStyles {
            private $facilities;
            
            public function __construct($facilities) {
                $this->facilities = $facilities;
            }
            
            public function collection() {
                return $this->facilities;
            }
            
            public function headings(): array {
                return [
                    'ID',
                    'Name',
                    'Email',
                    'Type',
                    'District',
                    'Region',
                    'Phone',
                    'Address',
                    'Status',
                    'Cold Storage',
                    'Handled By',
                    'Created Date'
                ];
            }
            
            public function map($facility): array {
                return [
                    $facility->id,
                    $facility->name,
                    $facility->email ?? '—',
                    $facility->facility_type ?? '—',
                    $facility->district ?? '—',
                    $facility->region ?? '—',
                    $facility->phone ?? '—',
                    $facility->address ?? '—',
                    $facility->is_active ? 'Active' : 'Inactive',
                    $facility->has_cold_storage ? 'Yes' : 'No',
                    $facility->handledby ? $facility->handledby->name : '—',
                    $facility->created_at?->format('Y-m-d'),
                ];
            }
            
            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet) {
                return [
                    1 => ['font' => ['bold' => true], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']]],
                ];
            }
        }, $filename);
    }
    
    /**
     * Display the LMIS Report interface for facilities
     */
    public function facilityLmisReport(Request $request)
    {
        // Get all facilities for the dropdown
        $facilities = Facility::select('id', 'name', 'facility_type', 'district', 'region')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        // Get all products for filtering
        $products = Product::select('id', 'name', 'productID')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        $reports = null;
        
        // If filters are provided, get the report data
        if ($request->filled(['month_year', 'facility_id'])) {
            $facilityId = $request->facility_id;
            $monthYear = $request->month_year;
            
            // Get or create facility monthly report
            $reports = FacilityMonthlyReport::where('facility_id', $facilityId)
                ->where('report_period', $monthYear)
                ->with([
                    'items.product.category:id,name',
                    'items.product.dosage:id,name',
                    'facility:id,name,facility_type,district,region',
                    'approvedBy:id,name',
                    'submittedBy:id,name',
                    'reviewedBy:id,name',
                    'rejectedBy:id,name'
                ])
                ->first();
        }
        
        return inertia('Report/FacilityLmisReport', [
            'reports' => $reports,
            'facilities' => $facilities,
            'products' => $products,
            'filters' => $request->only(['month_year', 'status', 'facility_id', 'product_id']),
        ]);
    }
    
    /**
     * Store facility LMIS report data
     */
    public function storeFacilityLmisReport(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'required|integer|min:1|max:12',
            'facility_id' => 'required|exists:facilities,id',
            'reports' => 'required|array',
            'reports.*.product_id' => 'required|exists:products,id',
            'reports.*.opening_balance' => 'required|numeric|min:0',
            'reports.*.stock_received' => 'required|numeric|min:0',
            'reports.*.stock_issued' => 'required|numeric|min:0',
            'reports.*.positive_adjustments' => 'nullable|numeric|min:0',
            'reports.*.negative_adjustments' => 'nullable|numeric|min:0',
            'reports.*.stockout_days' => 'nullable|integer|min:0|max:31',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $year = $request->input('year');
                $month = $request->input('month');
                $facilityId = $request->input('facility_id');
                $reportPeriod = sprintf('%04d-%02d', $year, $month);
                
                // Get or create the monthly report
                $monthlyReport = FacilityMonthlyReport::firstOrCreate([
                    'facility_id' => $facilityId,
                    'report_period' => $reportPeriod,
                ], [
                    'status' => 'draft',
                ]);

                $createdCount = 0;
                $updatedCount = 0;

                foreach ($request->input('reports') as $reportData) {
                    $existingItem = $monthlyReport->items()
                        ->where('product_id', $reportData['product_id'])
                        ->first();

                    if ($existingItem) {
                        $existingItem->update($reportData);
                        $updatedCount++;
                    } else {
                        $monthlyReport->items()->create($reportData);
                        $createdCount++;
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'LMIS report saved successfully',
                    'data' => [
                        'created_count' => $createdCount,
                        'updated_count' => $updatedCount,
                        'report_id' => $monthlyReport->id,
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to store facility LMIS report', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to save LMIS report: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Submit facility LMIS report for approval
     */
    public function submitFacilityLmisReport(Request $request)
    {
        $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer',
            'facility_id' => 'required|exists:facilities,id',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $year = $request->input('year');
                $month = $request->input('month');
                $facilityId = $request->input('facility_id');
                $reportPeriod = sprintf('%04d-%02d', $year, $month);
                
                $monthlyReport = FacilityMonthlyReport::where('facility_id', $facilityId)
                    ->where('report_period', $reportPeriod)
                    ->where('status', 'draft')
                    ->firstOrFail();

                $monthlyReport->update([
                    'status' => 'submitted',
                    'submitted_by' => auth()->id(),
                    'submitted_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'LMIS report submitted successfully for approval',
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to submit facility LMIS report', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit LMIS report: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generate facility LMIS report from movements
     */
    public function generateFacilityLmisReportFromMovements(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'required|integer|min:1|max:12',
            'facility_id' => 'required|exists:facilities,id',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $year = $request->input('year');
                $month = $request->input('month');
                $facilityId = $request->input('facility_id');
                $reportPeriod = sprintf('%04d-%02d', $year, $month);
                
                // Check if report already exists
                $existingReport = FacilityMonthlyReport::where('facility_id', $facilityId)
                    ->where('report_period', $reportPeriod)
                    ->first();
                
                if ($existingReport && $existingReport->status !== 'draft') {
                    return response()->json([
                        'success' => false,
                        'message' => 'A report for this period already exists and cannot be regenerated.'
                    ], 422);
                }

                // Get or create the monthly report
                $monthlyReport = FacilityMonthlyReport::firstOrCreate([
                    'facility_id' => $facilityId,
                    'report_period' => $reportPeriod,
                ], [
                    'status' => 'draft',
                ]);

                // Get the facility
                $facility = Facility::findOrFail($facilityId);
                
                // Get all eligible products for this facility
                $eligibleProducts = $facility->eligibleProducts()->get();
                
                $createdCount = 0;
                $updatedCount = 0;
                $movementsProcessed = 0;

                foreach ($eligibleProducts as $product) {
                    // Calculate movements for this product in the given month
                    $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                    $endDate = Carbon::create($year, $month, 1)->endOfMonth();
                    
                    // Get opening balance (closing balance from previous month or current inventory)
                    $openingBalance = $this->calculateOpeningBalance($facilityId, $product->id, $startDate);
                    
                    // Get received quantities (transfers, orders)
                    $stockReceived = $this->calculateStockReceived($facilityId, $product->id, $startDate, $endDate);
                    
                    // Get issued quantities (dispenses, transfers out)
                    $stockIssued = $this->calculateStockIssued($facilityId, $product->id, $startDate, $endDate);
                    
                    // Calculate closing balance
                    $closingBalance = $openingBalance + $stockReceived - $stockIssued;
                    
                    $reportData = [
                        'product_id' => $product->id,
                        'opening_balance' => $openingBalance,
                        'stock_received' => $stockReceived,
                        'stock_issued' => $stockIssued,
                        'positive_adjustments' => 0,
                        'negative_adjustments' => 0,
                        'closing_balance' => $closingBalance,
                        'stockout_days' => 0, // This would need to be calculated based on historical data
                    ];

                    $existingItem = $monthlyReport->items()
                        ->where('product_id', $product->id)
                        ->first();

                    if ($existingItem) {
                        $existingItem->update($reportData);
                        $updatedCount++;
                    } else {
                        $monthlyReport->items()->create($reportData);
                        $createdCount++;
                    }
                    
                    $movementsProcessed++;
                }

                return response()->json([
                    'success' => true,
                    'message' => 'LMIS report generated successfully from facility movements',
                    'data' => [
                        'created_count' => $createdCount,
                        'updated_count' => $updatedCount,
                        'total_products' => $eligibleProducts->count(),
                        'movements_processed' => $movementsProcessed,
                        'report_id' => $monthlyReport->id,
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to generate facility LMIS report from movements', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate LMIS report: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create/edit facility LMIS report interface
     */
    public function createFacilityLmisReport(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('n'));
        $facilityId = $request->get('facility_id');
        
        if (!$facilityId) {
            return redirect()->route('reports.facility-lmis-report')
                ->with('error', 'Please select a facility first.');
        }
        
        $facility = Facility::findOrFail($facilityId);
        $reportPeriod = sprintf('%04d-%02d', $year, $month);
        
        // Get or create the monthly report for this period
        $monthlyReport = FacilityMonthlyReport::firstOrCreate([
            'facility_id' => $facilityId,
            'report_period' => $reportPeriod,
        ], [
            'status' => 'draft',
        ]);
        
        // Get eligible products for this facility type
        $eligibleProducts = $facility->eligibleProducts()->select('products.id', 'products.name')->get();
        
        return inertia('Report/FacilityLmisReportCreate', [
            'monthlyReport' => $monthlyReport->load([
                'items.product.category:id,name',
                'items.product.dosage:id,name'
            ]),
            'facility' => $facility,
            'eligibleProducts' => $eligibleProducts,
            'year' => $year,
            'month' => $month,
        ]);
    }
    
    /**
     * Helper method to calculate opening balance
     */
    private function calculateOpeningBalance($facilityId, $productId, $startDate)
    {
        // This is a simplified calculation - in practice, you might need more complex logic
        // to get the actual opening balance from inventory or previous reports
        return 0;
    }
    
    /**
     * Helper method to calculate stock received
     */
    private function calculateStockReceived($facilityId, $productId, $startDate, $endDate)
    {
        // Calculate received quantities from transfers and orders
        $transfersReceived = DB::table('transfer_items')
            ->join('transfers', 'transfers.id', '=', 'transfer_items.transfer_id')
            ->where('transfers.to_facility_id', $facilityId)
            ->where('transfer_items.product_id', $productId)
            ->where('transfers.status', 'delivered')
            ->whereBetween('transfers.delivered_at', [$startDate, $endDate])
            ->sum('transfer_items.received_quantity');
            
        $ordersReceived = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.facility_id', $facilityId)
            ->where('order_items.product_id', $productId)
            ->where('orders.status', 'received')
            ->whereBetween('orders.updated_at', [$startDate, $endDate])
            ->sum('order_items.received_quantity');
        
        return ($transfersReceived ?? 0) + ($ordersReceived ?? 0);
    }
    
    /**
     * Helper method to calculate stock issued
     */
    private function calculateStockIssued($facilityId, $productId, $startDate, $endDate)
    {
        // Calculate issued quantities from dispenses and transfers out
        $dispenseIssued = DB::table('dispence_items')
            ->join('dispences', 'dispences.id', '=', 'dispence_items.dispence_id')
            ->where('dispences.facility_id', $facilityId)
            ->where('dispence_items.product_id', $productId)
            ->whereBetween('dispences.created_at', [$startDate, $endDate])
            ->sum('dispence_items.quantity');
            
        $transfersIssued = DB::table('transfer_items')
            ->join('transfers', 'transfers.id', '=', 'transfer_items.transfer_id')
            ->where('transfers.from_facility_id', $facilityId)
            ->where('transfer_items.product_id', $productId)
            ->where('transfers.status', 'delivered')
            ->whereBetween('transfers.created_at', [$startDate, $endDate])
            ->sum('transfer_items.allocated_quantity');
        
        return ($dispenseIssued ?? 0) + ($transfersIssued ?? 0);
    }
}
