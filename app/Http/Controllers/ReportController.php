<?php

namespace App\Http\Controllers;

use App\Mail\PhysicalCountSubmitted;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\Category;
use App\Models\EligibleItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\MonthlyQuantityReceived;
use App\Models\MonthlyConsumptionReport;
use App\Models\MonthlyConsumptionItem;
use App\Models\PackingList;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Order;
use App\Models\Facility;
use App\Models\FacilityInventory;
use App\Models\FacilityInventoryItem;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\InventoryReport;
use App\Models\WarehouseAmc;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\ReceivedBackorder;
use App\Models\Liquidate;
use App\Models\Disposal;
use App\Models\PurchaseOrder;
use App\Models\ReceivedQuantity;
use App\Jobs\ProcessPhysicalCountApprovalJob;
use App\Models\IssueQuantityReport;
use App\Http\Resources\PhysicalCountReportResource;
use App\Models\District;
use App\Models\Region;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\FacilityMonthlyReport;
use App\Exports\WarehouseMonthlyReportExport;
use App\Models\PhysicalCountReport;

class ReportController extends Controller
{

    public function index(Request $request){
        return redirect()->route('reports.inventoryReportsUnified');
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

    public function inventoryReportsUnified(Request $request)
    {
        $regions = Region::orderBy('name')->get(['id', 'name']);
        $districts = District::orderBy('name')->get(['id', 'name', 'region']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name', 'region', 'district']);
        $facilities = Facility::orderBy('name')->get(['id', 'name', 'region', 'district']);
        $reportTypes = [
            ['value' => 'warehouse_inventory', 'label' => 'Warehouse Inventory Report'],
            ['value' => 'facility_monthly_consumption', 'label' => 'Facility Monthly Consumption'],
            ['value' => 'product_report', 'label' => 'Product Report'],
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
            'report_type' => 'required|in:warehouse_inventory,facility_monthly_consumption,product_report',
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

        if ($reportType === 'product_report') {
            $result = $this->getProductReportData($request);
            return response()->json(['success' => true, 'data' => $result]);
        }

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
     * Build product report data: count eligible products by category and supply class.
     * When filter is region-only: one row per region (name = region name).
     * When filter is region + district: one row per district (name = district name).
     * When filter includes facility: one row per facility (name = facility name).
     */
    private function getProductReportData(Request $request): array
    {
        $facilityIds = $this->resolveFacilityIdsFromFilters($request);
        if (empty($facilityIds)) {
            return ['rows' => [], 'category_columns' => [], 'supply_class_columns' => []];
        }

        $facilities = Facility::whereIn('id', $facilityIds)->get();
        $allCategories = collect();
        $allSupplyClasses = collect();
        $perFacilityRows = [];

        foreach ($facilities as $facility) {
            $products = Product::whereHas('eligible', function ($q) use ($facility) {
                $q->where('facility_type', $facility->facility_type);
            })->with('category')->get();

            $catCounts = [];
            $scCounts = [];

            foreach ($products as $product) {
                $catName = $product->category->name ?? 'Uncategorized';
                $catCounts[$catName] = ($catCounts[$catName] ?? 0) + 1;
                $allCategories->push($catName);

                $supplyClassValues = $product->supply_class;
                if (is_string($supplyClassValues)) {
                    $supplyClassValues = array_map('trim', explode(',', $supplyClassValues));
                }
                if (is_array($supplyClassValues)) {
                    foreach ($supplyClassValues as $sc) {
                        if ($sc !== '' && $sc !== null) {
                            $scCounts[$sc] = ($scCounts[$sc] ?? 0) + 1;
                            $allSupplyClasses->push($sc);
                        }
                    }
                }
            }

            $perFacilityRows[] = [
                'name' => $facility->name,
                'type' => 'facility',
                'region' => $facility->region ?? '',
                'district' => $facility->district ?? '',
                'total_products' => $products->count(),
                'categories' => $catCounts,
                'supply_classes' => $scCounts,
            ];
        }

        if ($request->filled('facility_id')) {
            $rows = $perFacilityRows;
        } elseif ($request->filled('district_id')) {
            $byDistrict = [];
            foreach ($perFacilityRows as $row) {
                $key = $row['district'];
                if (!isset($byDistrict[$key])) {
                    $byDistrict[$key] = [
                        'name' => $row['district'],
                        'type' => 'district',
                        'total_products' => 0,
                        'categories' => [],
                        'supply_classes' => [],
                    ];
                }
                $byDistrict[$key]['total_products'] += $row['total_products'];
                foreach ($row['categories'] as $c => $cnt) {
                    $byDistrict[$key]['categories'][$c] = ($byDistrict[$key]['categories'][$c] ?? 0) + $cnt;
                }
                foreach ($row['supply_classes'] as $s => $cnt) {
                    $byDistrict[$key]['supply_classes'][$s] = ($byDistrict[$key]['supply_classes'][$s] ?? 0) + $cnt;
                }
            }
            $rows = array_values($byDistrict);
        } else {
            $byRegion = [];
            foreach ($perFacilityRows as $row) {
                $key = $row['region'];
                if (!isset($byRegion[$key])) {
                    $byRegion[$key] = [
                        'name' => $row['region'],
                        'type' => 'region',
                        'total_products' => 0,
                        'categories' => [],
                        'supply_classes' => [],
                    ];
                }
                $byRegion[$key]['total_products'] += $row['total_products'];
                foreach ($row['categories'] as $c => $cnt) {
                    $byRegion[$key]['categories'][$c] = ($byRegion[$key]['categories'][$c] ?? 0) + $cnt;
                }
                foreach ($row['supply_classes'] as $s => $cnt) {
                    $byRegion[$key]['supply_classes'][$s] = ($byRegion[$key]['supply_classes'][$s] ?? 0) + $cnt;
                }
            }
            $rows = array_values($byRegion);
        }

        $categoryColumns = $allCategories->unique()->sort()->values()->toArray();
        $supplyClassColumns = $allSupplyClasses->merge($this->getAllProductSupplyClasses())->unique()->sort()->values()->toArray();

        return [
            'rows' => $rows,
            'category_columns' => $categoryColumns,
            'supply_class_columns' => $supplyClassColumns,
        ];
    }

    /**
     * All distinct supply class values from products (so Product Report always includes Supply Class columns).
     */
    private function getAllProductSupplyClasses(): array
    {
        $values = Product::pluck('supply_class')
            ->filter()
            ->flatMap(function ($v) {
                if (is_string($v)) {
                    return array_map('trim', explode(',', $v));
                }
                if (is_array($v)) {
                    return $v;
                }
                return [];
            })
            ->unique()
            ->filter(fn ($s) => $s !== '')
            ->sort()
            ->values()
            ->toArray();

        return $values;
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
        if ($reportType === 'unified') {
            return $this->getUnifiedInventoryReportRowsMerged($monthYear, $warehouseIds, $facilityIds, $request);
        }
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

    /**
     * Unified report: one table with all columns, data from warehouse inventory (and AMC) plus facility consumption.
     * Warehouse rows use InventoryReport (all columns); facility rows use facility_inventory_items.
     */
    private function getUnifiedInventoryReportRowsMerged(?string $monthYear, array $warehouseIds, array $facilityIds, Request $request): array
    {
        $rows = [];
        if (!empty($warehouseIds) && $monthYear) {
            $rows = array_merge($rows, $this->unifiedRowsFromWarehouseInventory($monthYear, $warehouseIds));
        }
        if (!empty($facilityIds)) {
            $rows = array_merge($rows, $this->unifiedRowsFromFacilityMonthlyConsumption($monthYear, $facilityIds, $request));
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
            'product' => fn ($q) => $q->select('id', 'name', 'category_id', 'dosage_id', 'supply_class')
                ->with(['category:id,name', 'dosage:id,name']),
            'warehouse:id,name',
        ]);
        if (!empty($warehouseIds)) {
            $query->whereIn('warehouse_id', $warehouseIds);
        }
        $reportItems = $query->get();

        $batchQuery = InventoryItem::with(['product:id,name', 'warehouse:id,name']);
        if (!empty($warehouseIds)) {
            $batchQuery->whereIn('warehouse_id', $warehouseIds);
        }
        $allBatches = $batchQuery->get()->groupBy(fn ($b) => $b->product_id . '-' . $b->warehouse_id);

        $rows = [];
        foreach ($reportItems as $item) {
            $productName = $item->product->name ?? '';
            $category = $item->product->category->name ?? '';
            $uom = $item->product->dosage->name ?? '';
            $warehouseName = $item->warehouse->name ?? '';
            $key = $item->product_id . '-' . $item->warehouse_id;
            $batches = $allBatches->get($key, collect());

            $productAgg = [
                'total_closing_balance' => (int) $item->total_closing_balance,
                'amc' => (int) ($item->average_monthly_consumption ?? 0),
                'mos' => $item->months_of_stock ?? '',
                'stockout_days' => 0,
                'unit_cost' => (float) ($item->unit_cost ?? 0),
                'total_cost' => (float) ($item->total_cost ?? 0),
            ];

            if ($batches->isEmpty()) {
                $rows[] = array_merge([
                    'item' => $productName,
                    'category' => $category,
                    'uom' => $uom,
                    'batch_no' => null,
                    'expiry_date' => null,
                    'beginning_balance' => (int) $item->beginning_balance,
                    'qty_received' => (int) $item->received_quantity,
                    'qty_issued' => (int) $item->issued_quantity,
                    'adjustment_neg' => (int) $item->negative_adjustment,
                    'adjustment_pos' => (int) $item->positive_adjustment,
                    'closing_balance' => (int) $item->closing_balance,
                    'warehouse_name' => $warehouseName,
                    'facility_name' => null,
                    'rowspan' => 1,
                    'is_first_batch' => true,
                ], $productAgg);
            } else {
                $batchCount = $batches->count();
                $first = true;
                foreach ($batches as $batch) {
                    $row = [
                        'item' => $first ? $productName : null,
                        'category' => $first ? $category : null,
                        'uom' => $first ? $uom : null,
                        'batch_no' => $batch->batch_number ?? null,
                        'expiry_date' => $batch->expiry_date ?? null,
                        'beginning_balance' => 0,
                        'qty_received' => 0,
                        'qty_issued' => 0,
                        'adjustment_neg' => 0,
                        'adjustment_pos' => 0,
                        'closing_balance' => (int) ($batch->quantity ?? 0),
                        'warehouse_name' => $warehouseName,
                        'facility_name' => null,
                        'rowspan' => $first ? $batchCount : 0,
                        'is_first_batch' => $first,
                    ];
                    if ($first) {
                        $row = array_merge($row, $productAgg);
                    } else {
                        $row = array_merge($row, [
                            'total_closing_balance' => null,
                            'amc' => null,
                            'mos' => null,
                            'stockout_days' => null,
                            'unit_cost' => null,
                            'total_cost' => null,
                        ]);
                    }
                    $rows[] = $row;
                    $first = false;
                }
            }
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
