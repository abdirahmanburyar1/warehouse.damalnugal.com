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
use App\Models\OrderItem;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\InventoryAllocation;
use App\Models\Facility;
use App\Models\FacilityInventory;
use App\Models\FacilityInventoryItem;
use App\Models\FacilityMonthlyReport;
use App\Models\FacilityMonthlyReportItem;
use App\Models\FacilityInventoryMovement;
use App\Models\FacilityType;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\InventoryReport;
use App\Models\InventoryReportItem;
use App\Models\WarehouseAmc;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\ReceivedBackorder;
use App\Models\Liquidate;
use App\Models\Disposal;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PackingListItem;
use App\Models\ReceivedQuantity;
use App\Models\IssuedQuantity;
use App\Jobs\ProcessPhysicalCountApprovalJob;
use App\Models\IssueQuantityReport;
use App\Http\Resources\PhysicalCountReportResource;
use App\Models\District;
use App\Models\EmailNotificationSetting;
use App\Models\Region;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\WarehouseMonthlyReportExport;
use App\Models\PhysicalCountReport;
use App\Models\Asset;
use App\Models\AssetItem;
use App\Models\AssetCategory;

class ReportController extends Controller
{

    public function index(Request $request){
        return redirect()->route('reports.inventoryReportsUnified');
    }

    /**
     * Facilities list report: paginated facilities with filters (region, district, type, status).
     * Region and district come from the facilities table; district filter is dependent on region.
     */
    public function facilitiesList(Request $request)
    {
        $perPage = (int) ($request->per_page ?? 25);
        $query = Facility::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where(function ($q2) use ($request) {
                    $q2->where('name', 'like', '%'.$request->search.'%')
                        ->orWhere('facility_type', 'like', '%'.$request->search.'%')
                        ->orWhere('district', 'like', '%'.$request->search.'%')
                        ->orWhere('region', 'like', '%'.$request->search.'%');
                });
            })
            ->when($request->filled('region'), function ($q) use ($request) {
                $regions = is_array($request->region) ? $request->region : [$request->region];
                $q->whereIn('region', $regions);
            })
            ->when($request->filled('district'), function ($q) use ($request) {
                $districts = is_array($request->district) ? $request->district : [$request->district];
                $q->whereIn('district', $districts);
            })
            ->when($request->filled('facility_type'), function ($q) use ($request) {
                $types = is_array($request->facility_type) ? $request->facility_type : [$request->facility_type];
                $q->whereIn('facility_type', $types);
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('is_active', $request->status === 'Active');
            })
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->date_to);
            });

        $facilities = $query->orderBy('name')->paginate($perPage, ['*'], 'page', $request->page ?? 1);
        $facilities->setPath(url()->current());

        $summary = [
            'total_facilities' => Facility::count(),
            'by_status' => [
                'Active' => Facility::where('is_active', true)->count(),
                'Inactive' => Facility::where('is_active', false)->count(),
            ],
            'by_district' => Facility::select('district')->distinct()->pluck('district')->filter()->keyBy(fn ($d) => $d)->map(fn () => 1)->toArray(),
            'by_type' => Facility::select('facility_type')->distinct()->pluck('facility_type')->filter()->keyBy(fn ($t) => $t)->map(fn () => 1)->toArray(),
        ];

        $regions = Facility::whereNotNull('region')->where('region', '!=', '')->distinct()->orderBy('region')->pluck('region')->toArray();
        $districts = District::orderBy('name')->pluck('name')->toArray();
        $facilityTypes = FacilityType::where('is_active', true)->pluck('name')->toArray();

        return Inertia::render('Report/Facilities/FacilitiesList', [
            'facilities' => $facilities,
            'filters' => $request->only(['search', 'region', 'district', 'facility_type', 'status', 'date_from', 'date_to', 'per_page', 'page']),
            'filterOptions' => [
                'regions' => $regions,
                'districts' => $districts,
                'facility_types' => $facilityTypes,
                'statuses' => ['Active', 'Inactive'],
            ],
            'summary' => $summary,
        ]);
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
                    if (empty($user->email)) {
                        continue;
                    }
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
            ['value' => 'facility_monthly_consumption', 'label' => 'Facility LMIS report'],
            ['value' => 'report_submission_rate', 'label' => 'Report Submission Rate'],
            ['value' => 'product_report', 'label' => 'Product Report'],
            ['value' => 'liquidation_disposal', 'label' => 'Liquidation & Disposal'],
            ['value' => 'expiry_report', 'label' => 'Expiry Report'],
            ['value' => 'facilities_report', 'label' => 'Facilities Report'],
            ['value' => 'order_report', 'label' => 'Order Report'],
            ['value' => 'transfer_report', 'label' => 'Transfer Report'],
            ['value' => 'procurement_report', 'label' => 'Procurement Report'],
            ['value' => 'asset_report', 'label' => 'Asset Report'],
        ];
        $reportPeriodOptions = [
            ['value' => 'monthly', 'label' => 'Monthly'],
            ['value' => 'bi-monthly', 'label' => 'Bi-monthly'],
            ['value' => 'quarterly', 'label' => 'Quarterly'],
            ['value' => 'six_months', 'label' => 'Six months'],
            ['value' => 'yearly', 'label' => 'Yearly'],
        ];
        return Inertia::render('Report/InventoryReportsUnified', [
            'regions' => $regions,
            'districts' => $districts,
            'warehouses' => $warehouses,
            'facilities' => $facilities,
            'reportTypes' => $reportTypes,
            'reportPeriodOptions' => $reportPeriodOptions,
            'filters' => $request->only(['region_id', 'district_id', 'warehouse_id', 'facility_id', 'report_type', 'report_period', 'year', 'month']),
        ]);
    }

    /**
     * Data endpoint for consolidated inventory reports. Returns unified rows based on report_type and filters.
     */
    public function inventoryReportsUnifiedData(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:warehouse_inventory,facility_monthly_consumption,report_submission_rate,product_report,liquidation_disposal,expiry_report,facilities_report,order_report,transfer_report,procurement_report,asset_report',
            'region_id' => 'nullable|exists:regions,id',
            'district_id' => 'nullable|exists:districts,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'facility_id' => 'nullable|exists:facilities,id',
            'report_period' => 'nullable|in:monthly,bi-monthly,quarterly,six_months,yearly',
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $hasLocationFilter = $request->filled('region_id')
            || $request->filled('district_id')
            || $request->filled('warehouse_id')
            || $request->filled('facility_id');

        $reportType = $request->report_type;

        // Warehouse Inventory: allow with only year+month (no location required); show all warehouses
        $warehouseInventoryNeedsOnlyPeriod = $reportType === 'warehouse_inventory'
            && $request->filled('year')
            && $request->filled('month');

        // Liquidation & Disposal: allow with only year+month (no location required); show all warehouses for that period
        $liquidationDisposalNeedsOnlyPeriod = $reportType === 'liquidation_disposal'
            && ($request->filled('year') || $request->filled('month'));

        if (!$hasLocationFilter && !$warehouseInventoryNeedsOnlyPeriod && !$liquidationDisposalNeedsOnlyPeriod) {
            $data = $reportType === 'warehouse_inventory' ? [$this->getWarehouseInventoryEmptyRow()] : [];
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Please select at least one filter (Region, District, Warehouse or Facility).',
            ]);
        }

        if ($reportType === 'product_report') {
            $result = $this->getProductReportData($request);
            return response()->json(['success' => true, 'data' => $result]);
        }

        if ($reportType === 'liquidation_disposal') {
            $result = $this->getLiquidationDisposalReportData($request);
            return response()->json(['success' => true, 'data' => $result]);
        }

        if ($reportType === 'facility_monthly_consumption') {
            if (!$request->filled('facility_id')) {
                return response()->json([
                    'success' => false,
                    'data' => ['rows' => []],
                    'message' => 'For Facility LMIS report, you must select Region, District, and Facility.',
                ]);
            }
        }

        if ($reportType === 'expiry_report') {
            $hasFacilityOrWarehouse = $request->filled('facility_id') || $request->filled('warehouse_id');
            if (!$hasFacilityOrWarehouse) {
                return response()->json([
                    'success' => false,
                    'data' => ['rows' => []],
                    'message' => 'For Expiry Report, please select a Facility or Warehouse.',
                ]);
            }
            $result = $this->getExpiryReportData($request);
            return response()->json(['success' => true, 'data' => $result]);
        }

        if ($reportType === 'facilities_report') {
            $result = $this->getFacilitiesReportData($request);
            return response()->json(['success' => true, 'data' => $result]);
        }

        if ($reportType === 'order_report') {
            $result = $this->getOrderReportData($request);
            return response()->json(['success' => true, 'data' => $result]);
        }

        if ($reportType === 'transfer_report') {
            $result = $this->getTransferReportData($request);
            return response()->json(['success' => true, 'data' => $result]);
        }

        if ($reportType === 'procurement_report') {
            $warehouseIds = $this->resolveWarehouseIdsFromFilters($request);
            if (empty($warehouseIds)) {
                return response()->json([
                    'success' => false,
                    'data' => ['rows' => [], 'summary' => $this->getProcurementReportSummary([])],
                    'message' => 'Procurement Report requires Region, District or Warehouse.',
                ]);
            }
            $result = $this->getProcurementReportData($request, $warehouseIds);
            return response()->json(['success' => true, 'data' => $result]);
        }

        if ($reportType === 'asset_report') {
            $facilityIds = $this->resolveFacilityIdsFromFilters($request);
            if (empty($facilityIds)) {
                return response()->json([
                    'success' => false,
                    'data' => [
                        'rows' => [],
                        'summary' => $this->getAssetReportSummary(['total_assets' => 0, 'by_category' => array_fill_keys(self::ASSET_REPORT_CATEGORIES, ['total' => 0, 'functioning' => 0, 'not_functioning' => 0])]),
                        'category_columns' => self::ASSET_REPORT_CATEGORIES,
                        'aggregation_level' => null,
                        'name_column_label' => 'Facility Name',
                    ],
                    'message' => 'Asset Report requires Region, District or Facility.',
                ]);
            }
            $result = $this->getAssetReportData($request, $facilityIds);
            return response()->json(['success' => true, 'data' => $result]);
        }

        if ($reportType === 'report_submission_rate') {
            $facilityIds = $this->resolveFacilityIdsFromFilters($request);
            if (empty($facilityIds)) {
                return response()->json([
                    'success' => false,
                    'data' => ['rows' => []],
                    'message' => 'Report Submission Rate requires Region, District or Facility.',
                ]);
            }
            $reportPeriod = $request->input('report_period', 'monthly');
            $year = $request->filled('year') ? (int) $request->year : null;
            $month = $request->filled('month') ? (int) $request->month : null;
            if (!$year || !$month) {
                return response()->json([
                    'success' => false,
                    'data' => ['rows' => []],
                    'message' => 'Report Submission Rate requires Report Period, Year, and Month/Period.',
                ]);
            }
            $validPeriodMonths = [
                'monthly' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
                'bi-monthly' => [1, 3, 5, 7, 9, 11],
                'quarterly' => [1, 4, 7, 10],
                'six_months' => [1, 7],
                'yearly' => [1],
            ];
            $allowedMonths = $validPeriodMonths[$reportPeriod] ?? $validPeriodMonths['monthly'];
            if (!in_array($month, $allowedMonths, true)) {
                return response()->json([
                    'success' => false,
                    'data' => ['rows' => []],
                    'message' => 'For the selected Report Period, Month must be a valid period start (e.g. 1, 4, 7, 10 for Quarterly).',
                ]);
            }
            $result = $this->getReportSubmissionRateData($request, $facilityIds);
            return response()->json(['success' => true, 'data' => $result]);
        }

        $monthYear = null;

        if ($reportType === 'warehouse_inventory' || $reportType === 'facility_monthly_consumption') {
            $reportPeriod = $request->input('report_period', 'monthly');
            $year = $request->filled('year') ? (int) $request->year : null;
            $month = $request->filled('month') ? (int) $request->month : null;
            $reportLabel = $reportType === 'warehouse_inventory' ? 'Warehouse Inventory Report' : 'Facility LMIS report';
            if (!$year || !$month) {
                return response()->json([
                    'success' => true,
                    'data' => [$this->getWarehouseInventoryEmptyRow()],
                    'message' => "For {$reportLabel}, please select Report Period, Year, and Month.",
                ]);
            }
            $validPeriodMonths = [
                'monthly' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
                'bi-monthly' => [1, 3, 5, 7, 9, 11],
                'quarterly' => [1, 4, 7, 10],
                'six_months' => [1, 7],
                'yearly' => [1],
            ];
            $allowedMonths = $validPeriodMonths[$reportPeriod] ?? $validPeriodMonths['monthly'];
            if (!in_array($month, $allowedMonths, true)) {
                return response()->json([
                    'success' => true,
                    'data' => [$this->getWarehouseInventoryEmptyRow()],
                    'message' => 'For the selected Report Period, Month must be a valid period start (e.g. 1,4,7,10 for Quarterly).',
                ]);
            }
            $reportMonth = $this->getReportMonthForPeriod($reportPeriod, $month);
            $periodStart = sprintf('%04d-%02d', $year, $month);
            $periodEnd = sprintf('%04d-%02d', $year, $reportMonth);
            if ($reportType === 'warehouse_inventory') {
                $monthYear = $this->resolveWarehouseInventoryMonthYear($periodStart, $periodEnd, $reportPeriod);
            } else {
                $monthYear = $this->resolveFacilityLmisMonthYear($periodStart, $periodEnd);
            }
        } else {
            if ($request->filled('year') && $request->filled('month')) {
                $monthYear = sprintf('%04d-%02d', (int) $request->year, (int) $request->month);
            } elseif ($request->filled('year')) {
                $monthYear = (string) $request->year;
            }
        }

        $warehouseIds = $this->resolveWarehouseIdsFromFilters($request);
        $facilityIds = $this->resolveFacilityIdsFromFilters($request);
        // Warehouse inventory: filtered by report period only, ignore region/district/warehouse
        if ($reportType === 'warehouse_inventory') {
            $warehouseIds = [];
        }
        $data = $this->getUnifiedInventoryReportRows($reportType, $monthYear, $request->warehouse_id, $request->facility_id, $warehouseIds, $facilityIds, $request);

        // When warehouse inventory or Facility LMIS has no data, show the table with one zero row instead of empty
        $message = null;
        $reportMeta = null;
        if ($reportType === 'warehouse_inventory' && is_array($data) && empty($data)) {
            $data = [$this->getWarehouseInventoryEmptyRow()];
            if ($monthYear) {
                $report = InventoryReport::where('month_year', $monthYear)->first();
                $message = !$report
                    ? 'No inventory report for this month. Generate it from Settings → Report Schedules (Monthly received → Issue quantities → Inventory monthly report).'
                    : 'No rows in report for this month/filters. Table shown with zeros.';
            }
        }
        if ($reportType === 'facility_monthly_consumption' && is_array($data) && empty($data)) {
            $data = [$this->getWarehouseInventoryEmptyRow()];
            $message = 'No Facility LMIS report for this period/facility. Table shown with zeros.';
        }
        if ($reportType === 'warehouse_inventory' && $monthYear) {
            $report = InventoryReport::where('month_year', $monthYear)->first();
            if ($report) {
                $reportMeta = [
                    'report_id' => $report->id,
                    'report_status' => $report->status,
                    'report_can_edit' => $report->canBeEdited(),
                    'report_can_submit' => $report->canBeSubmitted(),
                    'report_can_review' => $report->status === 'submitted',
                    'report_can_approve_reject' => $report->status === 'under_review',
                    'rejection_reason' => $report->rejection_reason ?? null,
                    'stockout_days' => (int) ($report->stockout_days ?? 0),
                    'months_of_stock' => $report->months_of_stock ?? '',
                    'adjustment_neg' => (int) ($report->negative_adjustment ?? 0),
                    'adjustment_pos' => (int) ($report->positive_adjustment ?? 0),
                ];
            }
        }
        if ($reportType === 'facility_monthly_consumption' && $monthYear && !empty($facilityIds)) {
            $fmr = FacilityMonthlyReport::where('report_period', $monthYear)
                ->whereIn('facility_id', $facilityIds)
                ->where('status', 'approved')
                ->with(['facility:id,name', 'approvedBy:id,name'])
                ->first();
            if ($fmr) {
                $reportMeta = [
                    'report_id' => $fmr->id,
                    'facility_id' => $fmr->facility_id,
                    'facility_name' => $fmr->facility?->name ?? null,
                    'report_period' => $fmr->report_period,
                    'report_status' => 'approved',
                ];
            }
        }

        return response()->json(array_filter([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'report_meta' => $reportMeta,
        ]));
    }

    /**
     * Get date range [start, end] for report period + year + period start month.
     * Used by order and transfer reports to filter by order_date / transfer_date.
     *
     * @return array{0: string|null, 1: string|null} [startDate, endDate] in Y-m-d, or [null, null] if insufficient params
     */
    private function getDateRangeForPeriod(Request $request): array
    {
        $year = $request->filled('year') ? (int) $request->year : null;
        $month = $request->filled('month') ? (int) $request->month : null;
        $reportPeriod = $request->input('report_period', 'monthly');

        if (!$year || !$month) {
            return [null, null];
        }

        $endMonth = $this->getReportMonthForPeriod($reportPeriod, $month);
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = Carbon::parse(sprintf('%04d-%02d-01', $year, $endMonth))->endOfMonth()->format('Y-m-d');

        return [$startDate, $endDate];
    }

    /**
     * For Facility LMIS: find the most recent report_period within the period that has a facility monthly report.
     * Uses facility_monthly_reports.report_period (Y-m format). whereBetween for period range.
     */
    private function resolveFacilityLmisMonthYear(string $periodStart, string $periodEnd): string
    {
        if ($periodStart === $periodEnd) {
            return $periodStart;
        }
        $report = FacilityMonthlyReport::whereBetween('report_period', [$periodStart, $periodEnd])
            ->orderBy('report_period', 'desc')
            ->first(['report_period']);
        return $report ? $report->report_period : $periodEnd;
    }

    /**
     * For warehouse inventory: find the most recent month_year within the period that has a report.
     * E.g. Jan-Mar (2026-01 to 2026-03): if only 2026-02 exists, use it; if 2026-01,02,03 exist, use 2026-03.
     */
    private function resolveWarehouseInventoryMonthYear(string $periodStart, string $periodEnd, string $reportPeriod): string
    {
        if ($periodStart === $periodEnd) {
            return $periodStart;
        }
        $report = InventoryReport::whereBetween('month_year', [$periodStart, $periodEnd])
            ->orderBy('month_year', 'desc')
            ->first(['month_year']);
        return $report ? $report->month_year : $periodEnd;
    }

    /**
     * Map report period + period start month to the report month (last month of period).
     * Inventory reports are stored per month; we show the end-of-period month for non-monthly.
     */
    private function getReportMonthForPeriod(string $reportPeriod, int $periodStartMonth): int
    {
        switch ($reportPeriod) {
            case 'monthly':
                return $periodStartMonth;
            case 'bi-monthly':
                return $periodStartMonth + 1; // Jan-Feb → 2, Mar-Apr → 4, ...
            case 'quarterly':
                return $periodStartMonth + 2; // Jan-Mar → 3, Apr-Jun → 6, ...
            case 'six_months':
                return $periodStartMonth === 1 ? 6 : 12; // Jan-Jun → 6, Jul-Dec → 12
            case 'yearly':
                return 12;
            default:
                return $periodStartMonth;
        }
    }

    /**
     * One placeholder row with zeros for warehouse inventory report when there is no data.
     */
    private function getWarehouseInventoryEmptyRow(): array
    {
        return [
            'report_item_id' => null,
            'item' => '–',
            'category' => '–',
            'uom' => '–',
            'batch_no' => null,
            'expiry_date' => null,
            'beginning_balance' => 0,
            'qty_received' => 0,
            'qty_issued' => 0,
            'adjustment_neg' => 0,
            'adjustment_pos' => 0,
            'closing_balance' => 0,
            'total_closing_balance' => 0,
            'amc' => 0,
            'mos' => '–',
            'stockout_days' => 0,
            'unit_cost' => 0,
            'total_cost' => 0,
            'warehouse_name' => '–',
            'facility_name' => null,
            'rowspan' => 1,
            'is_first_batch' => true,
        ];
    }

    /**
     * Update a single inventory report item (item-level fields: adjustments, stockout_days at product level).
     */
    public function updateInventoryReportItem(Request $request, int $id)
    {
        $item = InventoryReportItem::with('report')->findOrFail($id);
        $validated = $request->validate([
            'adjustment_neg' => 'nullable|integer|min:0',
            'adjustment_pos' => 'nullable|integer|min:0',
            'stockout_days' => 'nullable|integer|min:0|max:366',
        ]);
        if (Schema::hasColumn('inventory_report_items', 'negative_adjustment') && array_key_exists('adjustment_neg', $validated)) {
            $item->negative_adjustment = (int) ($validated['adjustment_neg'] ?? 0);
        }
        if (Schema::hasColumn('inventory_report_items', 'positive_adjustment') && array_key_exists('adjustment_pos', $validated)) {
            $item->positive_adjustment = (int) ($validated['adjustment_pos'] ?? 0);
        }
        // stockout_days is product-level: update this item and all other items for same product in this report
        if (Schema::hasColumn('inventory_report_items', 'stockout_days') && array_key_exists('stockout_days', $validated)) {
            $days = (int) ($validated['stockout_days'] ?? 0);
            $item->stockout_days = $days;
            InventoryReportItem::where('inventory_report_id', $item->inventory_report_id)
                ->where('product_id', $item->product_id)
                ->where('id', '!=', $item->id)
                ->update(['stockout_days' => $days]);
        }
        // Closing balance = beginning + received - issued - negative_adjustment + positive_adjustment
        $neg = (int) ($item->negative_adjustment ?? 0);
        $pos = (int) ($item->positive_adjustment ?? 0);
        $closingBalance = (int) $item->beginning_balance + (int) $item->received_quantity - (int) $item->issued_quantity - (int) ($item->other_quantity_out ?? 0) - $neg + $pos;
        $item->closing_balance = $closingBalance;
        $item->total_closing_balance = $closingBalance;
        // Recalculate total cost = unit cost × closing balance
        if (Schema::hasColumn('inventory_report_items', 'unit_cost') && Schema::hasColumn('inventory_report_items', 'total_cost')) {
            $unitCost = (float) ($item->unit_cost ?? 0);
            $item->total_cost = round($unitCost * abs($closingBalance), 2);
        }
        $item->save();
        return response()->json(['success' => true, 'item' => $item->fresh(), 'product_id' => $item->product_id]);
    }

    /**
     * Update inventory report (report-level fields: adjustments, stockout_days, months_of_stock).
     * Editable without status restriction; permissions can be added later.
     */
    public function updateInventoryReport(Request $request, int $id)
    {
        $report = InventoryReport::findOrFail($id);
        $validated = $request->validate([
            'stockout_days' => 'nullable|integer|min:0|max:366',
            'months_of_stock' => 'nullable|string|max:255',
            'adjustment_neg' => 'nullable|integer|min:0',
            'adjustment_pos' => 'nullable|integer|min:0',
        ]);
        $update = [];
        if (array_key_exists('stockout_days', $validated) && Schema::hasColumn('inventory_reports', 'stockout_days')) {
            $update['stockout_days'] = (int) $validated['stockout_days'];
        }
        if (array_key_exists('months_of_stock', $validated) && Schema::hasColumn('inventory_reports', 'months_of_stock')) {
            $update['months_of_stock'] = $validated['months_of_stock'] === '' ? null : $validated['months_of_stock'];
        }
        if (array_key_exists('adjustment_neg', $validated) && Schema::hasColumn('inventory_reports', 'negative_adjustment')) {
            $update['negative_adjustment'] = (int) $validated['adjustment_neg'];
        }
        if (array_key_exists('adjustment_pos', $validated) && Schema::hasColumn('inventory_reports', 'positive_adjustment')) {
            $update['positive_adjustment'] = (int) $validated['adjustment_pos'];
        }
        if (!empty($update)) {
            $report->update($update);
        }
        return response()->json(['success' => true, 'report' => $report->fresh()]);
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
     * Report Submission Rate: per-facility expected vs actual submitted facility_monthly_reports,
     * with on-time vs late breakdown. Uses submitted_at and report_period.
     * Config from Settings → Report Submission Rate (expected reports, ontime/late rules).
     */
    private function getReportSubmissionRateData(Request $request, array $facilityIds): array
    {
        $year = (int) $request->year;
        $month = (int) $request->month;
        $periodType = $request->input('report_period', 'monthly');

        $config = EmailNotificationSetting::getReportSubmissionRateConfig();
        $expectedPerMonth = (int) ($config['expected_reports'] ?? 1);
        $ontimeDay = (int) ($config['ontime_day'] ?? 5);

        $monthsPerPeriod = [
            'monthly' => 1,
            'bi-monthly' => 2,
            'quarterly' => 3,
            'six_months' => 6,
            'yearly' => 12,
        ];
        $monthsInPeriod = $monthsPerPeriod[$periodType] ?? 1;
        $expectedCount = $expectedPerMonth * $monthsInPeriod;

        $reportMonth = $this->getReportMonthForPeriod($periodType, $month);
        $periodStart = sprintf('%04d-%02d', $year, $month);
        $periodEnd = sprintf('%04d-%02d', $year, $reportMonth);

        $facilities = Facility::whereIn('id', $facilityIds)->orderBy('name')->get(['id', 'name']);
        $rows = [];

        foreach ($facilities as $facility) {
            $reports = FacilityMonthlyReport::query()
                ->where('facility_id', $facility->id)
                ->whereBetween('report_period', [$periodStart, $periodEnd])
                ->whereIn('status', ['submitted', 'approved'])
                ->whereNotNull('submitted_at')
                ->get(['id', 'report_period', 'submitted_at']);

            $actualCount = $reports->count();
            $onTimeCount = 0;
            foreach ($reports as $r) {
                $nextMonth = Carbon::parse($r->report_period . '-01')->addMonth();
                $deadline = $nextMonth->copy()->day(min($ontimeDay, $nextMonth->daysInMonth))->endOfDay();
                if ($r->submitted_at && $r->submitted_at->lte($deadline)) {
                    $onTimeCount++;
                }
            }
            $lateCount = $actualCount - $onTimeCount;
            $rate = $expectedCount > 0 ? round(($actualCount / $expectedCount) * 100, 1) : 0;
            $onTimePct = $actualCount > 0 ? round(($onTimeCount / $actualCount) * 100, 1) : 0;
            $latePct = $actualCount > 0 ? round(($lateCount / $actualCount) * 100, 1) : 0;

            $rows[] = [
                'facility_id' => $facility->id,
                'facility_name' => $facility->name,
                'expected' => $expectedCount,
                'actual' => $actualCount,
                'submission_rate' => $rate,
                'on_time_count' => $onTimeCount,
                'on_time_pct' => $onTimePct,
                'late_count' => $lateCount,
                'late_pct' => $latePct,
            ];
        }

        return ['rows' => $rows];
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
     * Build Liquidation & Disposal report: one row per warehouse with totals and reasons (Missing, Lost, Damage, Expired).
     * Liquidation: warehouse from liquidates.warehouse (parent).
     * Disposal: warehouse from disposal_items.warehouse (parent disposals.warehouse is not set by the app).
     */
    private function getLiquidationDisposalReportData(Request $request): array
    {
        $warehouseIds = $this->resolveWarehouseIdsFromFilters($request);
        if (empty($warehouseIds)) {
            $warehouseIds = Warehouse::orderBy('name')->pluck('id')->toArray();
        }

        $warehouses = Warehouse::whereIn('id', $warehouseIds)->orderBy('name')->get(['id', 'name']);
        $warehouseNames = $warehouses->pluck('name')->toArray();

        // Smart default: use current year and current month when not provided
        $year = $request->filled('year') ? (int) $request->year : (int) date('Y');
        $month = $request->filled('month') ? (int) $request->month : (int) date('n');
        $monthYear = sprintf('%04d-%02d', $year, $month);

        $rows = [];
        foreach ($warehouses as $warehouse) {
            $name = $warehouse->name;

            // Liquidation: parent has warehouse (set e.g. from physical count); some flows only set it on items (liquidate_items has no warehouse column)
            $liquidateQuery = Liquidate::where('warehouse', $name)->where('status', 'approved')
                ->whereRaw('DATE_FORMAT(liquidated_at, "%Y-%m") = ?', [$monthYear]);
            $liquidateIds = $liquidateQuery->pluck('id');

            $liqItemNo = 0;
            $liqValue = 0;
            $liqMissing = 0;
            $liqLost = 0;
            if ($liquidateIds->isNotEmpty()) {
                $liqAgg = \App\Models\LiquidateItem::whereIn('liquidate_id', $liquidateIds)
                    ->selectRaw('COALESCE(SUM(quantity), 0) as item_no, COALESCE(SUM(total_cost), 0) as total_value')
                    ->first();
                $liqItemNo = (int) ($liqAgg->item_no ?? 0);
                $liqValue = (float) ($liqAgg->total_value ?? 0);

                $byType = \App\Models\LiquidateItem::whereIn('liquidate_id', $liquidateIds)
                    ->select('type', DB::raw('COALESCE(SUM(quantity), 0) as qty'))
                    ->groupBy('type')
                    ->get();
                foreach ($byType as $t) {
                    $normalized = strtolower(trim((string) $t->type));
                    if ($normalized === 'missing') {
                        $liqMissing += (int) $t->qty;
                    } elseif ($normalized === 'lost') {
                        $liqLost += (int) $t->qty;
                    }
                }
            }

            // Disposal: warehouse is stored on disposal_items, not on disposals (parent never set in SupplyController/ExpiredController)
            $disposalItemQuery = \App\Models\DisposalItem::where('warehouse', $name)
                ->whereHas('disposal', function ($q) use ($monthYear) {
                    $q->where('status', 'approved')
                        ->whereRaw('DATE_FORMAT(disposed_at, "%Y-%m") = ?', [$monthYear]);
                });

            $dispItemNo = 0;
            $dispValue = 0;
            $dispDamage = 0;
            $dispExpired = 0;
            $dispAgg = (clone $disposalItemQuery)->selectRaw('COALESCE(SUM(quantity), 0) as item_no, COALESCE(SUM(total_cost), 0) as total_value')->first();
            if ($dispAgg) {
                $dispItemNo = (int) ($dispAgg->item_no ?? 0);
                $dispValue = (float) ($dispAgg->total_value ?? 0);
            }
            $byType = (clone $disposalItemQuery)->select('type', DB::raw('COALESCE(SUM(quantity), 0) as qty'))->groupBy('type')->get();
            foreach ($byType as $t) {
                $normalized = strtolower(trim((string) $t->type));
                if ($normalized === 'damage' || $normalized === 'damaged') {
                    $dispDamage += (int) $t->qty;
                } elseif ($normalized === 'expired') {
                    $dispExpired += (int) $t->qty;
                }
            }

            $rows[] = [
                'warehouse_name' => $name,
                'total_liquated_item_no' => $liqItemNo,
                'total_liquated_value' => round($liqValue, 2),
                'total_disposed_item_no' => $dispItemNo,
                'total_disposed_value' => round($dispValue, 2),
                'liquidation_missing' => $liqMissing,
                'liquidation_lost' => $liqLost,
                'disposal_damage' => $dispDamage,
                'disposal_expired' => $dispExpired,
            ];
        }

        return ['rows' => $rows];
    }

    /**
     * Build Expiry report: same table for both facility and warehouse inventory.
     * Only includes the explicitly selected facility and/or warehouse (required by caller).
     */
    private function getExpiryReportData(Request $request): array
    {
        $facilityIds = $request->filled('facility_id')
            ? [(int) $request->facility_id]
            : [];
        $warehouseIds = $request->filled('warehouse_id')
            ? [(int) $request->warehouse_id]
            : [];
        if (empty($facilityIds) && empty($warehouseIds)) {
            return ['rows' => []];
        }

        $today = Carbon::today()->startOfDay();
        $sixMonths = $today->copy()->addMonths(6);
        $oneYear = $today->copy()->addYear();
        $rows = [];

        // Facility inventory (FacilityInventoryItem)
        foreach (Facility::whereIn('id', $facilityIds)->orderBy('name')->get(['id', 'name']) as $facility) {
            $baseQuery = FacilityInventoryItem::query()
                ->whereHas('inventory', fn ($q) => $q->where('facility_id', $facility->id))
                ->where('quantity', '>', 0)
                ->whereNotNull('expiry_date');

            $expiredQuery = (clone $baseQuery)->where('expiry_date', '<', $today);
            $within6Query = (clone $baseQuery)->where('expiry_date', '>=', $today)->where('expiry_date', '<=', $sixMonths);
            $within1YearQuery = (clone $baseQuery)->where('expiry_date', '>', $sixMonths)->where('expiry_date', '<=', $oneYear);

            $rows[] = [
                'type' => 'facility',
                'name' => $facility->name ?: 'Facility #' . $facility->id,
                'expiring_1_year_item_no' => (int) $within1YearQuery->sum('quantity'),
                'expiring_1_year_value' => round((float) $within1YearQuery->sum('total_cost'), 2),
                'expiring_6_months_item_no' => (int) $within6Query->sum('quantity'),
                'expiring_6_months_value' => round((float) $within6Query->sum('total_cost'), 2),
                'expired_item_no' => (int) $expiredQuery->sum('quantity'),
                'expired_value' => round((float) $expiredQuery->sum('total_cost'), 2),
            ];
        }

        // Warehouse inventory (InventoryItem), same as Expiry module
        foreach (Warehouse::whereIn('id', $warehouseIds)->orderBy('name')->get(['id', 'name']) as $warehouse) {
            $baseQuery = InventoryItem::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('quantity', '>', 0)
                ->whereNotNull('expiry_date');

            $expiredQuery = (clone $baseQuery)->where('expiry_date', '<', $today);
            $within6Query = (clone $baseQuery)->where('expiry_date', '>=', $today)->where('expiry_date', '<=', $sixMonths);
            $within1YearQuery = (clone $baseQuery)->where('expiry_date', '>', $sixMonths)->where('expiry_date', '<=', $oneYear);

            $rows[] = [
                'type' => 'warehouse',
                'name' => $warehouse->name ?: 'Warehouse #' . $warehouse->id,
                'expiring_1_year_item_no' => (int) $within1YearQuery->sum('quantity'),
                'expiring_1_year_value' => round((float) $within1YearQuery->sum('total_cost'), 2),
                'expiring_6_months_item_no' => (int) $within6Query->sum('quantity'),
                'expiring_6_months_value' => round((float) $within6Query->sum('total_cost'), 2),
                'expired_item_no' => (int) $expiredQuery->sum('quantity'),
                'expired_value' => round((float) $expiredQuery->sum('total_cost'), 2),
            ];
        }

        return ['rows' => $rows];
    }

    /**
     * Resolve districts for reporting (by region and/or district filter).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\District>
     */
    private function resolveDistrictsFromFilters(Request $request)
    {
        $query = District::query()->orderBy('name');
        if ($request->filled('district_id')) {
            $query->where('id', $request->district_id);
        }
        if ($request->filled('region_id')) {
            $regionName = Region::find($request->region_id)?->name;
            if ($regionName) {
                $query->where('region', $regionName);
            }
        }
        return $query->get();
    }

    /** Facility type labels for Facilities Report (order and canonical names). */
    private const FACILITIES_REPORT_TYPES = [
        'Primary Health Unit',
        'Health Center',
        'District Hospital',
        'Regional Hospital',
    ];

    /**
     * Normalize facility_type for grouping (case-insensitive, trim; map "Health Centre" to "Health Center").
     */
    private function normalizeFacilityTypeForReport(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }
        $t = strtolower(trim($type));
        foreach (self::FACILITIES_REPORT_TYPES as $canonical) {
            if (strtolower($canonical) === $t || str_replace(' ', '', strtolower($canonical)) === str_replace(' ', '', $t)) {
                return $canonical;
            }
        }
        if ($t === 'health centre') {
            return 'Health Center';
        }
        return $type;
    }

    /**
     * Build Facilities Report: one row per district with total facilities, counts by type, by activation, cold storage.
     */
    private function getFacilitiesReportData(Request $request): array
    {
        $districts = $this->resolveDistrictsFromFilters($request);
        if ($districts->isEmpty()) {
            return ['rows' => [], 'facility_type_columns' => self::FACILITIES_REPORT_TYPES];
        }

        $rows = [];
        foreach ($districts as $district) {
            $districtName = $district->name ?: 'District #' . $district->id;
            $facilities = Facility::where('district', $districtName)->get();

            $total = $facilities->count();
            $byType = array_fill_keys(self::FACILITIES_REPORT_TYPES, 0);
            foreach ($facilities as $f) {
                $canonical = $this->normalizeFacilityTypeForReport($f->facility_type);
                if ($canonical && isset($byType[$canonical])) {
                    $byType[$canonical]++;
                }
            }
            $active = $facilities->where('is_active', true)->count();
            $notActive = $facilities->where('is_active', false)->count();
            $coldStorage = $facilities->where('has_cold_storage', true)->count();

            $rows[] = [
                'district_name' => $districtName,
                'total_facilities' => $total,
                'primary_health_unit' => $byType['Primary Health Unit'],
                'health_center' => $byType['Health Center'],
                'district_hospital' => $byType['District Hospital'],
                'regional_hospital' => $byType['Regional Hospital'],
                'active' => $active,
                'not_active' => $notActive,
                'cold_storage_count' => $coldStorage,
            ];
        }

        return [
            'rows' => $rows,
            'facility_type_columns' => self::FACILITIES_REPORT_TYPES,
        ];
    }

    /**
     * Order Report: one row per facility with Total Orders, Completed/Rejected, Delivery Status (On-time/Late), Items and QTY Fulfillment (Good/Fair/Poor).
     */
    private function getOrderReportData(Request $request): array
    {
        $facilityIds = $this->resolveFacilityIdsFromFilters($request);
        if (empty($facilityIds)) {
            return ['rows' => [], 'summary' => $this->getOrderReportSummary([])];
        }

        $orderQuery = Order::query()->whereIn('facility_id', $facilityIds);
        [$dateStart, $dateEnd] = $this->getDateRangeForPeriod($request);
        if ($dateStart && $dateEnd) {
            $orderQuery->whereBetween('order_date', [$dateStart, $dateEnd]);
        } elseif ($request->filled('year')) {
            $orderQuery->whereYear('order_date', (int) $request->year);
        }

        $facilities = Facility::whereIn('id', $facilityIds)->orderBy('name')->get(['id', 'name']);
        $rows = [];
        $allReceived = 0;
        $allRejected = 0;
        $allOnTime = 0;
        $allLate = 0;

        foreach ($facilities as $facility) {
            $baseOrders = (clone $orderQuery)->where('facility_id', $facility->id);
            $totalOrders = $baseOrders->count();
            $receivedOrders = (clone $baseOrders)->where('status', 'received')->count();
            $rejectedOrders = (clone $baseOrders)->where('status', 'rejected')->count();

            $completedPct = $totalOrders > 0 ? round($receivedOrders / $totalOrders * 100, 1) : 0;
            $rejectedPct = $totalOrders > 0 ? round($rejectedOrders / $totalOrders * 100, 1) : 0;

            $receivedOrderIds = Order::query()->where('facility_id', $facility->id)->where('status', 'received');
            [$dateStart, $dateEnd] = $this->getDateRangeForPeriod($request);
            if ($dateStart && $dateEnd) {
                $receivedOrderIds->whereBetween('order_date', [$dateStart, $dateEnd]);
            } elseif ($request->filled('year')) {
                $receivedOrderIds->whereYear('order_date', (int) $request->year);
            }
            $receivedOrderIds = $receivedOrderIds->pluck('id')->toArray();

            $ontimeCount = 0;
            $lateCount = 0;
            if (!empty($receivedOrderIds)) {
                $receivedOrdersWithDate = Order::whereIn('id', $receivedOrderIds)
                    ->whereNotNull('received_at')
                    ->whereNotNull('expected_date')
                    ->get(['id', 'expected_date', 'received_at']);
                foreach ($receivedOrdersWithDate as $o) {
                    $cutoff = Carbon::parse($o->expected_date)->addDays(2)->endOfDay();
                    $receivedAt = Carbon::parse($o->received_at);
                    if ($receivedAt->lte($cutoff)) {
                        $ontimeCount++;
                    } else {
                        $lateCount++;
                    }
                }
            }

            $deliveredTotal = $ontimeCount + $lateCount;
            $ontimePct = $deliveredTotal > 0 ? round($ontimeCount / $deliveredTotal * 100, 1) : 0;
            $latePct = $deliveredTotal > 0 ? round($lateCount / $deliveredTotal * 100, 1) : 0;

            $allReceived += $receivedOrders;
            $allRejected += $rejectedOrders;
            $allOnTime += $ontimeCount;
            $allLate += $lateCount;

            $orderIdsForFacility = (clone $orderQuery)->where('facility_id', $facility->id)->pluck('id')->toArray();
            $itemsGood = 0;
            $itemsFair = 0;
            $itemsPoor = 0;
            $qtyGood = 0;
            $qtyFair = 0;
            $qtyPoor = 0;

            if (!empty($orderIdsForFacility)) {
                $orderItems = OrderItem::query()
                    ->whereIn('order_id', $orderIdsForFacility)
                    ->get(['order_id', 'quantity', 'quantity_to_release', 'received_quantity']);
                $byOrder = [];
                foreach ($orderItems as $item) {
                    $byOrder[$item->order_id][] = $item;
                }
                foreach ($byOrder as $items) {
                    $totalItems = count($items);
                    $suppliedItems = 0;
                    $totalQty = 0;
                    $releasedQty = 0;
                    foreach ($items as $it) {
                        $q = (int) $it->quantity;
                        $rel = (int) ($it->quantity_to_release ?? 0);
                        $rec = (int) ($it->received_quantity ?? 0);
                        $totalQty += $q;
                        $releasedQty += $rel > 0 ? $rel : $rec;
                        if ($q <= 0 || $rel >= $q || $rec >= $q) {
                            $suppliedItems++;
                        }
                    }
                    $itemsRate = $totalItems > 0 ? ($suppliedItems / $totalItems) * 100 : 0;
                    $qtyRate = $totalQty > 0 ? ($releasedQty / $totalQty) * 100 : 0;
                    if ($itemsRate > 90) {
                        $itemsGood++;
                    } elseif ($itemsRate >= 80) {
                        $itemsFair++;
                    } else {
                        $itemsPoor++;
                    }
                    if ($qtyRate > 90) {
                        $qtyGood++;
                    } elseif ($qtyRate >= 80) {
                        $qtyFair++;
                    } else {
                        $qtyPoor++;
                    }
                }
            }

            $itemsTotal = $itemsGood + $itemsFair + $itemsPoor;
            $itemsGoodPct = $itemsTotal > 0 ? round($itemsGood / $itemsTotal * 100, 0) : 0;
            $itemsFairPct = $itemsTotal > 0 ? round($itemsFair / $itemsTotal * 100, 0) : 0;
            $itemsPoorPct = $itemsTotal > 0 ? round($itemsPoor / $itemsTotal * 100, 0) : 0;
            $qtyTotal = $qtyGood + $qtyFair + $qtyPoor;
            $qtyGoodPct = $qtyTotal > 0 ? round($qtyGood / $qtyTotal * 100, 0) : 0;
            $qtyFairPct = $qtyTotal > 0 ? round($qtyFair / $qtyTotal * 100, 0) : 0;
            $qtyPoorPct = $qtyTotal > 0 ? round($qtyPoor / $qtyTotal * 100, 0) : 0;

            $rows[] = [
                'facility_name' => $facility->name ?: 'Facility #' . $facility->id,
                'total_orders' => $totalOrders,
                'completed_orders' => $receivedOrders,
                'completed_pct' => $completedPct,
                'rejected_orders' => $rejectedOrders,
                'rejected_pct' => $rejectedPct,
                'delivery_ontime_count' => $ontimeCount,
                'delivery_ontime_pct' => $ontimePct,
                'delivery_late_count' => $lateCount,
                'delivery_late_pct' => $latePct,
                'items_good_pct' => $itemsGoodPct,
                'items_fair_pct' => $itemsFairPct,
                'items_poor_pct' => $itemsPoorPct,
                'qty_good_pct' => $qtyGoodPct,
                'qty_fair_pct' => $qtyFairPct,
                'qty_poor_pct' => $qtyPoorPct,
            ];
        }

        $totalOrdersAll = array_sum(array_column($rows, 'total_orders'));
        $summary = $this->getOrderReportSummary([
            'total_orders' => $totalOrdersAll,
            'received' => $allReceived,
            'rejected' => $allRejected,
            'total_delivered' => $allOnTime + $allLate,
            'on_time' => $allOnTime,
            'late' => $allLate,
        ]);

        return ['rows' => $rows, 'summary' => $summary];
    }

    private function getOrderReportSummary(array $totals): array
    {
        return [
            'total_orders' => $totals['total_orders'] ?? 0,
            'received' => $totals['received'] ?? 0,
            'rejected' => $totals['rejected'] ?? 0,
            'total_delivered' => $totals['total_delivered'] ?? 0,
            'on_time' => $totals['on_time'] ?? 0,
            'late' => $totals['late'] ?? 0,
        ];
    }

    /** Transfer type labels for Transfer Report (chart and table). */
    private const TRANSFER_TYPE_W2F = 'Warehouse to Facility';
    private const TRANSFER_TYPE_W2W = 'Warehouse to Warehouse';
    private const TRANSFER_TYPE_F2W = 'Facility to Warehouse';
    private const TRANSFER_TYPE_F2F = 'Facility to Facility';

    /**
     * Derive transfer type from from/to warehouse/facility IDs.
     */
    private function deriveTransferType(Transfer $t): ?string
    {
        if ($t->from_warehouse_id && $t->to_facility_id) {
            return self::TRANSFER_TYPE_W2F;
        }
        if ($t->from_warehouse_id && $t->to_warehouse_id) {
            return self::TRANSFER_TYPE_W2W;
        }
        if ($t->from_facility_id && $t->to_warehouse_id) {
            return self::TRANSFER_TYPE_F2W;
        }
        if ($t->from_facility_id && $t->to_facility_id) {
            return self::TRANSFER_TYPE_F2F;
        }
        return null;
    }

    /**
     * Normalize transfer reason for report. All values are read from inventory_allocations.transfer_reason.
     * Maps stored reason text to report categories: Wrong Item, Overstock, Soon to Expire, Slow Moving.
     */
    private function normalizeTransferReason(?string $reason): ?string
    {
        if ($reason === null || $reason === '') {
            return null;
        }
        $r = strtolower(trim($reason));
        // Wrong Item: from transfer_reason (e.g. "Wrong Item" from reasons table)
        if (str_contains($r, 'wrong item') || str_contains($r, 'wrong')) {
            return 'Wrong Item';
        }
        if (str_contains($r, 'overstock')) {
            return 'Overstock';
        }
        if (str_contains($r, 'expire') || str_contains($r, 'expir')) {
            return 'Soon to Expire';
        }
        if (str_contains($r, 'slow')) {
            return 'Slow Moving';
        }
        return $reason;
    }

    /**
     * Transfer Report: one row per facility with Total/Completed/Rejected transfers, Transfer Reasons (Overstock, Soon to Expire, Slow Moving), Transfer Type counts.
     */
    private function getTransferReportData(Request $request): array
    {
        $facilityIds = $this->resolveFacilityIdsFromFilters($request);
        if (empty($facilityIds)) {
            return ['rows' => [], 'summary' => $this->getTransferReportSummary([])];
        }

        $baseQuery = Transfer::query()->where(function ($q) use ($facilityIds) {
            $q->whereIn('from_facility_id', $facilityIds)->orWhereIn('to_facility_id', $facilityIds);
        });
        [$dateStart, $dateEnd] = $this->getDateRangeForPeriod($request);
        if ($dateStart && $dateEnd) {
            $baseQuery->whereBetween('transfer_date', [$dateStart, $dateEnd]);
        } elseif ($request->filled('year')) {
            $baseQuery->whereYear('transfer_date', (int) $request->year);
        }

        $facilities = Facility::whereIn('id', $facilityIds)->orderBy('name')->get(['id', 'name']);
        $rows = [];
        $summaryTotal = 0;
        $summaryReceived = 0;
        $summaryRejected = 0;
        $summaryWrongItem = 0;
        $summaryOverstock = 0;
        $summarySlowMoving = 0;
        $summaryW2F = 0;
        $summaryW2W = 0;
        $summaryF2W = 0;
        $summaryF2F = 0;

        foreach ($facilities as $facility) {
            $q = (clone $baseQuery)->where(function ($q) use ($facility) {
                $q->where('from_facility_id', $facility->id)->orWhere('to_facility_id', $facility->id);
            });
            $totalTransfers = $q->count();
            $received = (clone $q)->where('status', 'received')->count();
            $rejected = (clone $q)->where('status', 'rejected')->count();
            $completedPct = $totalTransfers > 0 ? round($received / $totalTransfers * 100, 1) : 0;
            $rejectedPct = $totalTransfers > 0 ? round($rejected / $totalTransfers * 100, 1) : 0;

            $transferIds = (clone $q)->pluck('id')->toArray();
            $wrongItem = 0;
            $overstock = 0;
            $soonToExpire = 0;
            $slowMoving = 0;
            $w2f = 0;
            $w2w = 0;
            $f2w = 0;
            $f2f = 0;

            if (!empty($transferIds)) {
                $transferItemIds = TransferItem::whereIn('transfer_id', $transferIds)->pluck('id')->toArray();
                if (!empty($transferItemIds)) {
                    $reasonColumn = Schema::hasColumn('inventory_allocations', 'transfer_reason')
                        ? 'transfer_reason'
                        : (Schema::hasColumn('inventory_allocations', 'reason') ? 'reason' : null);
                    if ($reasonColumn) {
                        $reasonValues = DB::table('inventory_allocations')
                            ->whereIn('transfer_item_id', $transferItemIds)
                            ->whereNotNull($reasonColumn)
                            ->where($reasonColumn, '!=', '')
                            ->pluck($reasonColumn);
                        foreach ($reasonValues as $reasonText) {
                            $norm = $this->normalizeTransferReason($reasonText);
                            if ($norm === 'Wrong Item') {
                                $wrongItem++;
                            } elseif ($norm === 'Overstock') {
                                $overstock++;
                            } elseif ($norm === 'Soon to Expire') {
                                $soonToExpire++;
                            } elseif ($norm === 'Slow Moving') {
                                $slowMoving++;
                            }
                        }
                    }
                }
                $transfersForType = Transfer::whereIn('id', $transferIds)->get(['from_warehouse_id', 'to_warehouse_id', 'from_facility_id', 'to_facility_id']);
                foreach ($transfersForType as $t) {
                    $type = $this->deriveTransferType($t);
                    if ($type === self::TRANSFER_TYPE_W2F) {
                        $w2f++;
                    } elseif ($type === self::TRANSFER_TYPE_W2W) {
                        $w2w++;
                    } elseif ($type === self::TRANSFER_TYPE_F2W) {
                        $f2w++;
                    } elseif ($type === self::TRANSFER_TYPE_F2F) {
                        $f2f++;
                    }
                }
            }

            $summaryTotal += $totalTransfers;
            $summaryReceived += $received;
            $summaryRejected += $rejected;
            $summaryWrongItem += $wrongItem;
            $summaryOverstock += $overstock;
            $summarySlowMoving += $slowMoving;
            $summaryW2F += $w2f;
            $summaryW2W += $w2w;
            $summaryF2W += $f2w;
            $summaryF2F += $f2f;

            $rows[] = [
                'facility_name' => $facility->name ?: 'Facility #' . $facility->id,
                'total_transfers' => $totalTransfers,
                'completed_transfers' => $received,
                'completed_pct' => $completedPct,
                'rejected_transfers' => $rejected,
                'rejected_pct' => $rejectedPct,
                'reason_wrong_item' => $wrongItem,
                'reason_overstock' => $overstock,
                'reason_soon_to_expire' => $soonToExpire,
                'reason_slow_moving' => $slowMoving,
                'type_warehouse_to_facility' => $w2f,
                'type_warehouse_to_warehouse' => $w2w,
                'type_facility_to_warehouse' => $f2w,
                'type_facility_to_facility' => $f2f,
            ];
        }

        $summary = $this->getTransferReportSummary([
            'total_transfers' => $summaryTotal,
            'received' => $summaryReceived,
            'rejected' => $summaryRejected,
            'reason_wrong_item' => $summaryWrongItem,
            'reason_overstock' => $summaryOverstock,
            'reason_slow_moving' => $summarySlowMoving,
            'warehouse_to_facility' => $summaryW2F,
            'warehouse_to_warehouse' => $summaryW2W,
            'facility_to_warehouse' => $summaryF2W,
            'facility_to_facility' => $summaryF2F,
        ]);

        return ['rows' => $rows, 'summary' => $summary];
    }

    private function getTransferReportSummary(array $totals): array
    {
        return [
            'total_transfers' => $totals['total_transfers'] ?? 0,
            'received' => $totals['received'] ?? 0,
            'rejected' => $totals['rejected'] ?? 0,
            'reason_wrong_item' => $totals['reason_wrong_item'] ?? 0,
            'reason_overstock' => $totals['reason_overstock'] ?? 0,
            'reason_slow_moving' => $totals['reason_slow_moving'] ?? 0,
            'warehouse_to_facility' => $totals['warehouse_to_facility'] ?? 0,
            'warehouse_to_warehouse' => $totals['warehouse_to_warehouse'] ?? 0,
            'facility_to_warehouse' => $totals['facility_to_warehouse'] ?? 0,
            'facility_to_facility' => $totals['facility_to_facility'] ?? 0,
        ];
    }

    /**
     * Procurement Report: one row per warehouse (POs + Packing Lists). Total POs, Completed/Rejected, Avg Lead Time,
     * Delivery Status (On-time/Late), Items & QTY Fulfillment (Good/Fair/Poor), Total POs Cost.
     */
    private function getProcurementReportData(Request $request, array $warehouseIds): array
    {
        $warehouses = Warehouse::whereIn('id', $warehouseIds)->orderBy('name')->get(['id', 'name']);
        $rows = [];
        $summaryTotal = 0;
        $summaryCompleted = 0;
        $summaryRejected = 0;
        $summaryOnTime = 0;
        $summaryLate = 0;
        $summaryCost = 0.0;
        $qtyGood = 0;
        $qtyFair = 0;
        $qtyPoor = 0;

        $poDateFilter = function ($q) use ($request) {
            if ($request->filled('year') && $request->filled('month')) {
                $monthStart = sprintf('%04d-%02d-01', (int) $request->year, (int) $request->month);
                $monthEnd = Carbon::parse($monthStart)->endOfMonth()->format('Y-m-d');
                $q->whereBetween('po_date', [$monthStart, $monthEnd]);
            } elseif ($request->filled('year')) {
                $q->whereYear('po_date', (int) $request->year);
            }
        };

        foreach ($warehouses as $warehouse) {
            $poIds = PurchaseOrder::query()
                ->whereHas('packingLists.items', fn ($q) => $q->where('warehouse_id', $warehouse->id))
                ->when($request->filled('year') || $request->filled('month'), $poDateFilter)
                ->pluck('id')
                ->toArray();

            $totalPos = count($poIds);
            $completed = $totalPos > 0 ? PurchaseOrder::whereIn('id', $poIds)->where('status', 'completed')->count() : 0;
            $rejected = $totalPos > 0 ? PurchaseOrder::whereIn('id', $poIds)->where('status', 'rejected')->count() : 0;
            $completedPct = $totalPos > 0 ? round($completed / $totalPos * 100, 1) : 0;
            $rejectedPct = $totalPos > 0 ? round($rejected / $totalPos * 100, 1) : 0;

            $avgLeadTime = 0;
            $onTimeCount = 0;
            $lateCount = 0;
            $itemsGoodPct = 0;
            $itemsFairPct = 0;
            $itemsPoorPct = 0;
            $qtyGoodPct = 0;
            $qtyFairPct = 0;
            $qtyPoorPct = 0;
            $totalCost = 0.0;
            $whLeadTimeSum = 0;
            $whLeadTimeCount = 0;

            if ($totalPos > 0) {
                $pos = PurchaseOrder::with(['items', 'packingLists' => fn ($q) => $q->whereHas('items', fn ($q2) => $q2->where('warehouse_id', $warehouse->id))])
                    ->whereIn('id', $poIds)
                    ->get();

                $itemsGood = 0;
                $itemsFair = 0;
                $itemsPoor = 0;
                $qtyGoodN = 0;
                $qtyFairN = 0;
                $qtyPoorN = 0;

                foreach ($pos as $po) {
                    $totalCost += (float) $po->total_amount;

                    $plIds = $po->packingLists->pluck('id')->toArray();
                    $plReceivedDate = null;
                    if (!empty($plIds)) {
                        $latest = PackingList::whereIn('id', $plIds)->whereNotNull('approved_at')->orderByDesc('approved_at')->first();
                        $plReceivedDate = $latest?->approved_at;
                    }

                    if ($po->approved_at && $plReceivedDate) {
                        $days = (int) Carbon::parse($po->approved_at)->diffInDays(Carbon::parse($plReceivedDate), false);
                        $whLeadTimeSum += abs($days);
                        $whLeadTimeCount++;
                    }

                    if ($po->status === 'completed' && $po->expected_date && $plReceivedDate) {
                        $cutoff = Carbon::parse($po->expected_date)->addDays(2)->endOfDay();
                        if (Carbon::parse($plReceivedDate)->lte($cutoff)) {
                            $onTimeCount++;
                        } else {
                            $lateCount++;
                        }
                    }

                    $poItemIds = $po->items->pluck('id')->toArray();
                    $receivedByPoItem = PackingListItem::whereIn('po_item_id', $poItemIds)
                        ->where('warehouse_id', $warehouse->id)
                        ->selectRaw('po_item_id, SUM(quantity) as received_qty')
                        ->groupBy('po_item_id')
                        ->pluck('received_qty', 'po_item_id');

                    $totalOrderedQty = 0;
                    $totalReceivedQty = 0;
                    $suppliedItems = 0;
                    $totalItems = $po->items->count();
                    foreach ($po->items as $item) {
                        $ordered = (int) $item->quantity;
                        $received = (int) ($receivedByPoItem[$item->id] ?? 0);
                        $totalOrderedQty += $ordered;
                        $totalReceivedQty += $received;
                        if ($ordered <= 0 || $received >= $ordered) {
                            $suppliedItems++;
                        }
                    }

                    $itemsRate = $totalItems > 0 ? ($suppliedItems / $totalItems) * 100 : 0;
                    $qtyRate = $totalOrderedQty > 0 ? ($totalReceivedQty / $totalOrderedQty) * 100 : 0;

                    if ($itemsRate > 90) {
                        $itemsGood++;
                    } elseif ($itemsRate >= 80) {
                        $itemsFair++;
                    } else {
                        $itemsPoor++;
                    }
                    if ($qtyRate > 90) {
                        $qtyGoodN++;
                    } elseif ($qtyRate >= 80) {
                        $qtyFairN++;
                    } else {
                        $qtyPoorN++;
                    }
                }

                $ordersWithItems = $itemsGood + $itemsFair + $itemsPoor;
                if ($ordersWithItems > 0) {
                    $itemsGoodPct = round($itemsGood / $ordersWithItems * 100, 0);
                    $itemsFairPct = round($itemsFair / $ordersWithItems * 100, 0);
                    $itemsPoorPct = round($itemsPoor / $ordersWithItems * 100, 0);
                }
                $ordersWithQty = $qtyGoodN + $qtyFairN + $qtyPoorN;
                if ($ordersWithQty > 0) {
                    $qtyGoodPct = round($qtyGoodN / $ordersWithQty * 100, 0);
                    $qtyFairPct = round($qtyFairN / $ordersWithQty * 100, 0);
                    $qtyPoorPct = round($qtyPoorN / $ordersWithQty * 100, 0);
                }

                $avgLeadTime = $whLeadTimeCount > 0 ? (int) round($whLeadTimeSum / $whLeadTimeCount) : 0;
            }

            $summaryTotal += $totalPos;
            $summaryCompleted += $completed;
            $summaryRejected += $rejected;
            $summaryOnTime += $onTimeCount;
            $summaryLate += $lateCount;
            $summaryCost += $totalCost;
            if ($totalPos > 0) {
                $qtyGood += (int) round($totalPos * $qtyGoodPct / 100);
                $qtyFair += (int) round($totalPos * $qtyFairPct / 100);
                $qtyPoor += (int) round($totalPos * $qtyPoorPct / 100);
            }

            $deliveredTotal = $onTimeCount + $lateCount;
            $onTimePct = $deliveredTotal > 0 ? round($onTimeCount / $deliveredTotal * 100, 1) : 0;
            $latePct = $deliveredTotal > 0 ? round($lateCount / $deliveredTotal * 100, 1) : 0;

            $rows[] = [
                'warehouse_name' => $warehouse->name ?: 'Warehouse #' . $warehouse->id,
                'total_pos' => $totalPos,
                'completed_pos' => $completed,
                'completed_pct' => $completedPct,
                'rejected_pos' => $rejected,
                'rejected_pct' => $rejectedPct,
                'avg_lead_time_days' => $avgLeadTime,
                'delivery_ontime_count' => $onTimeCount,
                'delivery_ontime_pct' => $onTimePct,
                'delivery_late_count' => $lateCount,
                'delivery_late_pct' => $latePct,
                'items_good_pct' => $itemsGoodPct,
                'items_fair_pct' => $itemsFairPct,
                'items_poor_pct' => $itemsPoorPct,
                'qty_good_pct' => $qtyGoodPct,
                'qty_fair_pct' => $qtyFairPct,
                'qty_poor_pct' => $qtyPoorPct,
                'total_pos_cost' => round($totalCost, 2),
            ];
        }

        $totalDelivered = $summaryOnTime + $summaryLate;
        $summary = $this->getProcurementReportSummary([
            'total_pos' => $summaryTotal,
            'completed' => $summaryCompleted,
            'rejected' => $summaryRejected,
            'total_delivered' => $totalDelivered,
            'on_time' => $summaryOnTime,
            'late' => $summaryLate,
            'total_cost' => $summaryCost,
            'qty_good' => $qtyGood,
            'qty_fair' => $qtyFair,
            'qty_poor' => $qtyPoor,
        ]);

        return ['rows' => $rows, 'summary' => $summary];
    }

    private function getProcurementReportSummary(array $totals): array
    {
        return [
            'total_pos' => $totals['total_pos'] ?? 0,
            'completed' => $totals['completed'] ?? 0,
            'rejected' => $totals['rejected'] ?? 0,
            'total_delivered' => $totals['total_delivered'] ?? 0,
            'on_time' => $totals['on_time'] ?? 0,
            'late' => $totals['late'] ?? 0,
            'total_cost' => $totals['total_cost'] ?? 0,
            'qty_good' => $totals['qty_good'] ?? 0,
            'qty_fair' => $totals['qty_fair'] ?? 0,
            'qty_poor' => $totals['qty_poor'] ?? 0,
        ];
    }

    /**
     * Fixed asset report categories. All are always shown; DB categories map into these or "Others".
     */
    private const ASSET_REPORT_CATEGORIES = ['Medical Equipment', 'Furniture', 'IT Equipment', 'Vehicles', 'Others'];

    /**
     * Map a DB category name to one of the fixed asset report categories.
     */
    private function mapToAssetReportCategory(string $dbCategoryName): string
    {
        $name = strtolower(trim($dbCategoryName));
        if (str_contains($name, 'medical')) {
            return 'Medical Equipment';
        }
        if (str_contains($name, 'furniture')) {
            return 'Furniture';
        }
        if (str_contains($name, 'it ') || $name === 'it') {
            return 'IT Equipment';
        }
        if (str_contains($name, 'vehicle')) {
            return 'Vehicles';
        }
        return 'Others';
    }

    /**
     * Asset Report: rows aggregated by region, district, or facility based on filters.
     * - Region only: one row per region (region name)
     * - District: one row per district (district name)
     * - Facility: one row per facility (facility name)
     * Uses fixed categories: Medical Equipment, Furniture, IT Equipment, Vehicles, Others. All shown with zeros when empty.
     */
    private function getAssetReportData(Request $request, array $facilityIds): array
    {
        $aggregationLevel = $request->filled('facility_id') ? 'facility' : ($request->filled('district_id') ? 'district' : 'region');
        $facilities = Facility::whereIn('id', $facilityIds)
            ->orderBy('region')
            ->orderBy('district')
            ->orderBy('name')
            ->get(['id', 'name', 'region', 'district']);

        // Group facilities by aggregation key
        $groups = [];
        foreach ($facilities as $f) {
            if ($aggregationLevel === 'facility') {
                $key = 'f:' . $f->id;
                $label = $f->name;
            } elseif ($aggregationLevel === 'district') {
                $key = 'd:' . ($f->district ?? 'Unknown');
                $label = $f->district ?? 'Unknown';
            } else {
                $key = 'r:' . ($f->region ?? 'Unknown');
                $label = $f->region ?? 'Unknown';
            }
            if (!isset($groups[$key])) {
                $groups[$key] = ['label' => $label, 'facility_ids' => []];
            }
            $groups[$key]['facility_ids'][] = $f->id;
        }

        $categoryNames = self::ASSET_REPORT_CATEGORIES;
        $rows = [];
        $summaryTotal = 0;
        $summaryByCategory = array_fill_keys($categoryNames, ['total' => 0, 'functioning' => 0, 'not_functioning' => 0]);
        $functioningStatuses = ['Good', 'in_use', 'pending_approval', 'functioning'];
        $notFunctioningStatuses = ['Non-functional', 'maintenance', 'retired', 'disposed', 'not_functioning'];

        $nameColumnLabel = match ($aggregationLevel) {
            'region' => 'Region',
            'district' => 'District',
            default => 'Facility Name',
        };

        foreach ($groups as $group) {
            $facilityIdsInGroup = $group['facility_ids'];
            $baseQuery = AssetItem::query()
                ->whereHas('asset', function ($q) use ($facilityIdsInGroup) {
                    $q->whereIn('facility_id', $facilityIdsInGroup);
                })
                ->join('asset_categories', 'asset_items.asset_category_id', '=', 'asset_categories.id');

            $totalAssets = (int) (clone $baseQuery)->count();

            $byDbCategory = (clone $baseQuery)
                ->selectRaw("asset_categories.name as category_name,
                    count(asset_items.id) as total,
                    sum(case when asset_items.status in ('" . implode("','", array_map('addslashes', $functioningStatuses)) . "') then 1 else 0 end) as functioning,
                    sum(case when asset_items.status in ('" . implode("','", array_map('addslashes', $notFunctioningStatuses)) . "') then 1 else 0 end) as not_functioning")
                ->groupBy('asset_categories.name')
                ->get();

            $byCategory = [];
            foreach ($byDbCategory as $r) {
                $mapped = $this->mapToAssetReportCategory($r->category_name);
                if (!isset($byCategory[$mapped])) {
                    $byCategory[$mapped] = ['total' => 0, 'functioning' => 0, 'not_functioning' => 0];
                }
                $byCategory[$mapped]['total'] += (int) $r->total;
                $byCategory[$mapped]['functioning'] += (int) $r->functioning;
                $byCategory[$mapped]['not_functioning'] += (int) $r->not_functioning;
            }

            $row = [
                'facility_id' => $aggregationLevel === 'facility' && count($facilityIdsInGroup) === 1 ? $facilityIdsInGroup[0] : null,
                'facility_name' => $group['label'],
                'total_assets' => $totalAssets,
            ];
            foreach ($categoryNames as $catName) {
                $key = 'category_' . preg_replace('/\s+/', '_', strtolower($catName));
                $data = $byCategory[$catName] ?? null;
                $row[$key . '_total'] = $data ? (int) $data['total'] : 0;
                $row[$key . '_functioning'] = $data ? (int) $data['functioning'] : 0;
                $row[$key . '_not_functioning'] = $data ? (int) $data['not_functioning'] : 0;
                $summaryByCategory[$catName]['total'] += $row[$key . '_total'];
                $summaryByCategory[$catName]['functioning'] += $row[$key . '_functioning'];
                $summaryByCategory[$catName]['not_functioning'] += $row[$key . '_not_functioning'];
            }
            $rows[] = $row;
            $summaryTotal += $totalAssets;
        }

        $summary = $this->getAssetReportSummary([
            'total_assets' => $summaryTotal,
            'by_category' => $summaryByCategory,
        ]);

        return [
            'rows' => $rows,
            'summary' => $summary,
            'category_columns' => $categoryNames,
            'aggregation_level' => $aggregationLevel,
            'name_column_label' => $nameColumnLabel,
        ];
    }

    private function getAssetReportSummary(array $totals): array
    {
        $byCategory = $totals['by_category'] ?? [];
        return [
            'total_assets' => $totals['total_assets'] ?? 0,
            'by_category' => $byCategory,
        ];
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

    /**
     * Warehouse Inventory Report: one UI row per inventory_report_item (batch).
     * Grouped by product: Item, Category, UoM and aggregated columns (Total Closing Balance, AMC, MOS,
     * Unit cost, Total Cost, Stockout Days) use rowspan and are shown once per product.
     */
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
        $reportItems = $query->orderBy('product_id')->orderBy('id')->get();

        // Group by product_id for rowspan and aggregated values
        $byProduct = $reportItems->groupBy('product_id');
        $productAmcs = WarehouseAmc::where('month_year', $monthYear)
            ->whereIn('product_id', $byProduct->keys()->toArray())
            ->pluck('quantity', 'product_id')
            ->toArray();

        $rows = [];
        foreach ($reportItems as $index => $item) {
            $productId = $item->product_id;
            $group = $byProduct->get($productId);
            $rowspan = $group->count();
            $isFirstBatch = $group->first()->id === $item->id;

            $productName = $item->product?->name ?? '';
            $category = $item->product?->category?->name ?? '';
            $uom = $item->product?->dosage?->name ?? $item->uom ?? '';
            $warehouseName = $item->warehouse?->name ?? '';
            // stockout_days is product-level: get from group by product (one value per product)
            $stockoutDays = (int) ($group->max('stockout_days') ?? $report->stockout_days ?? 0);
            $itemAdjNeg = Schema::hasColumn('inventory_report_items', 'negative_adjustment') ? (int) ($item->negative_adjustment ?? 0) : null;
            $itemAdjPos = Schema::hasColumn('inventory_report_items', 'positive_adjustment') ? (int) ($item->positive_adjustment ?? 0) : null;
            $adjNeg = $itemAdjNeg !== null ? $itemAdjNeg : (int) ($report->negative_adjustment ?? 0);
            $adjPos = $itemAdjPos !== null ? $itemAdjPos : (int) ($report->positive_adjustment ?? 0);
            $beginningBalance = (int) $item->beginning_balance;
            $receivedQty = (int) $item->received_quantity;
            $issuedQty = (int) $item->issued_quantity;
            $closingBalance = (int) $item->closing_balance;
            $expiryDate = $item->expiry_date ? ($item->expiry_date instanceof \DateTimeInterface ? $item->expiry_date->format('Y-m-d') : $item->expiry_date) : null;

            // Product-level aggregates (only used when is_first_batch for rowspan cells)
            $totalClosingBalance = $group->sum('closing_balance');
            // Total cost = unit cost × closing balance per batch, then sum (recalculated, not from stored total_cost)
            $groupTotalCost = $group->sum(fn ($i) => (float) ($i->unit_cost ?? 0) * abs((int) ($i->closing_balance ?? 0)));
            $totalCost = round($groupTotalCost, 2);
            $amc = (int) ($productAmcs[$productId] ?? $group->max('average_monthly_consumption') ?? 0);
            $mos = $amc > 0 ? round($totalClosingBalance / $amc, 2) : null;
            $mosDisplay = $mos !== null ? (string) $mos : '–';

            $rows[] = [
                'product_id' => $productId,
                'item' => $productName,
                'category' => $category,
                'uom' => $uom,
                'batch_no' => $item->batch_number ?? null,
                'expiry_date' => $expiryDate,
                'beginning_balance' => $beginningBalance,
                'qty_received' => $receivedQty,
                'qty_issued' => $issuedQty,
                'adjustment_neg' => $adjNeg,
                'adjustment_pos' => $adjPos,
                'closing_balance' => $closingBalance,
                'warehouse_name' => $warehouseName,
                'facility_name' => null,
                'rowspan' => $rowspan,
                'is_first_batch' => $isFirstBatch,
                'report_item_id' => $item->id,
                'total_closing_balance' => $totalClosingBalance,
                'amc' => $amc,
                'mos' => $mosDisplay,
                'stockout_days' => $stockoutDays,
                'unit_cost' => (float) ($item->unit_cost ?? 0), // per batch (batches can differ)
                'total_cost' => $totalCost, // product-level: sum of batch total costs
            ];
        }
        return $rows;
    }

    /**
     * Sum of received_quantities.quantity per (product_id, warehouse_id, batch_number) for the given date range.
     * Key: "product_id-warehouse_id-batch_number" (empty string for null batch_number).
     *
     * @return array<string, int>
     */
    private function getReceivedQuantitiesPerBatch(Carbon $start, Carbon $end, array $warehouseIds): array
    {
        $query = ReceivedQuantity::query()
            ->whereBetween('received_at', [$start, $end])
            ->selectRaw('product_id, warehouse_id, COALESCE(batch_number, "") as batch_number, SUM(quantity) as total')
            ->groupBy('product_id', 'warehouse_id', 'batch_number');
        if (!empty($warehouseIds)) {
            $query->whereIn('warehouse_id', $warehouseIds);
        }
        $out = [];
        foreach ($query->get() as $row) {
            $key = $row->product_id . '-' . $row->warehouse_id . '-' . ($row->batch_number ?? '');
            $out[$key] = (int) $row->total;
        }
        return $out;
    }

    /**
     * Sum of issued_quantities.quantity per (product_id, warehouse_id, batch_number) for the given date range.
     * Key: "product_id-warehouse_id-batch_number" (empty string for null batch_number).
     *
     * @return array<string, int>
     */
    private function getIssuedQuantitiesPerBatch(Carbon $start, Carbon $end, array $warehouseIds): array
    {
        $query = IssuedQuantity::query()
            ->whereBetween('issued_date', [$start, $end])
            ->selectRaw('product_id, warehouse_id, COALESCE(batch_number, "") as batch_number, SUM(quantity) as total')
            ->groupBy('product_id', 'warehouse_id', 'batch_number');
        if (!empty($warehouseIds)) {
            $query->whereIn('warehouse_id', $warehouseIds);
        }
        $out = [];
        foreach ($query->get() as $row) {
            $key = $row->product_id . '-' . $row->warehouse_id . '-' . ($row->batch_number ?? '');
            $out[$key] = (int) $row->total;
        }
        return $out;
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
     * Facility LMIS report: product-level rows from facility_monthly_reports / facility_monthly_report_items.
     * AMC (Average Monthly Consumption) from monthly_consumption_reports / monthly_consumption_items via AmcCalculationService.
     * Link: facility_id + product_id. monthly_consumption_reports.month_year = facility_monthly_reports.report_period (Y-m).
     */
    private function unifiedRowsFromFacilityMonthlyConsumption(?string $monthYear, array $facilityIds, Request $request): array
    {
        if (!$monthYear || strlen($monthYear) < 7 || empty($facilityIds)) {
            return [];
        }
        $reports = FacilityMonthlyReport::query()
            ->where('report_period', $monthYear)
            ->whereIn('facility_id', $facilityIds)
            ->whereIn('status', ['submitted', 'approved', 'rejected', 'reviewed'])
            ->with(['facility:id,name'])
            ->get();
        if ($reports->isEmpty()) {
            return [];
        }
        $reportIds = $reports->pluck('id')->toArray();
        $reportById = $reports->keyBy('id');
        $reportItems = FacilityMonthlyReportItem::query()
            ->whereIn('parent_id', $reportIds)
            ->with([
                'product' => fn ($q) => $q->select('id', 'name', 'productID', 'category_id', 'dosage_id')
                    ->with(['category:id,name', 'dosage:id,name']),
            ])
            ->orderBy('product_id')
            ->orderBy('id')
            ->get();

        // Get AMC per facility+product from monthly_consumption_items (via AmcCalculationService)
        $amcByFacilityProduct = [];
        foreach ($reports as $report) {
            $facilityId = (int) $report->facility_id;
            $productIds = $reportItems->where('parent_id', $report->id)->pluck('product_id')->unique()->values()->toArray();
            if (! empty($productIds)) {
                $amcResults = app(\App\Services\AmcCalculationService::class)->calculateAmcForProducts(
                    $facilityId,
                    $productIds,
                    $monthYear
                );
                foreach ($amcResults as $productId => $result) {
                    $amcByFacilityProduct[$facilityId . '-' . $productId] = $result['amc'] ?? 0;
                }
            }
        }

        $rows = [];
        foreach ($reportItems as $item) {
            $report = $reportById->get($item->parent_id);
            $facilityId = $report ? (int) $report->facility_id : 0;
            $facilityName = $report && $report->relationLoaded('facility') ? ($report->facility->name ?? null) : null;
            $productId = $item->product_id;
            $amc = $amcByFacilityProduct[$facilityId . '-' . $productId] ?? 0;

            $rows[] = [
                'id' => $item->id,
                'product_id' => $productId,
                'item' => $item->product?->name ?? '',
                'product_id_code' => $item->product?->productID ?? '',
                'category' => $item->product?->category?->name ?? '',
                'uom' => $item->product?->dosage?->name ?? '',
                'opening_balance' => (float) ($item->opening_balance ?? 0),
                'stock_received' => (float) ($item->stock_received ?? 0),
                'stock_issued' => (float) ($item->stock_issued ?? 0),
                'positive_adjustments' => (float) ($item->positive_adjustments ?? 0),
                'negative_adjustments' => (float) ($item->negative_adjustments ?? 0),
                'closing_balance' => (float) ($item->closing_balance ?? 0),
                'stockout_days' => (int) ($item->stockout_days ?? 0),
                'amc' => (float) $amc,
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
        $regions = Region::orderBy('name')->get(['id', 'name']);
        $districts = District::orderBy('name')->get(['id', 'name', 'region']);

        $facilities = collect();
        $products = collect();
        $reports = null;

        // Only load facilities when region and district are selected (avoids 1000+ facility load)
        if ($request->filled(['region_id', 'district_id'])) {
            $regionName = Region::find($request->region_id)?->name;
            $districtName = District::find($request->district_id)?->name;
            if ($regionName && $districtName) {
                $facilities = Facility::select('id', 'name', 'facility_type', 'district', 'region')
                    ->where('is_active', true)
                    ->where('region', $regionName)
                    ->where('district', $districtName)
                    ->orderBy('name')
                    ->get();
            }
        }

        // Only load report and products when region, district, facility, and report period are all selected
        if ($request->filled(['region_id', 'district_id', 'facility_id', 'month_year'])) {
            $facilityId = $request->facility_id;
            $monthYear = $request->month_year;

            $reports = FacilityMonthlyReport::where('facility_id', $facilityId)
                ->where('report_period', $monthYear)
                ->whereIn('status', ['submitted', 'approved', 'rejected', 'reviewed'])
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

            if ($reports) {
                $products = Product::select('id', 'name', 'productID')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();

                // Attach AMC from monthly_consumption_reports/items via AmcCalculationService
                $productIds = $reports->items->pluck('product_id')->unique()->values()->toArray();
                $amcResults = app(\App\Services\AmcCalculationService::class)->calculateAmcForProducts(
                    $reports->facility_id,
                    $productIds,
                    $reports->report_period ?? $monthYear
                );
                foreach ($reports->items as $item) {
                    $result = $amcResults[$item->product_id] ?? [];
                    $item->setAttribute('amc', is_array($result) ? ($result['amc'] ?? 0) : $result);
                }
            }
        }

        return inertia('Report/FacilityLmisReport', [
            'reports' => $reports,
            'regions' => $regions,
            'districts' => $districts,
            'facilities' => $facilities,
            'products' => $products,
            'filters' => $request->only(['month_year', 'region_id', 'district_id', 'facility_id', 'product_id']),
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
     * Review facility LMIS report (submitted → reviewed).
     */
    public function reviewFacilityLmisReport(Request $request)
    {
        $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer',
            'facility_id' => 'required|exists:facilities,id',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $reportPeriod = sprintf('%04d-%02d', $request->year, $request->month);
                $report = FacilityMonthlyReport::where('facility_id', $request->facility_id)
                    ->where('report_period', $reportPeriod)
                    ->where('status', 'submitted')
                    ->firstOrFail();

                $report->update([
                    'status' => 'reviewed',
                    'reviewed_at' => now(),
                    'reviewed_by' => auth()->id(),
                ]);

                return response()->json(['success' => true, 'message' => 'Report marked under review.']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to review facility LMIS report', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve facility LMIS report (reviewed → approved).
     */
    public function approveFacilityLmisReport(Request $request)
    {
        $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer',
            'facility_id' => 'required|exists:facilities,id',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $reportPeriod = sprintf('%04d-%02d', $request->year, $request->month);
                $report = FacilityMonthlyReport::where('facility_id', $request->facility_id)
                    ->where('report_period', $reportPeriod)
                    ->where('status', 'reviewed')
                    ->firstOrFail();

                $report->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                ]);

                return response()->json(['success' => true, 'message' => 'Report approved.']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to approve facility LMIS report', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject facility LMIS report (submitted or reviewed → rejected).
     */
    public function rejectFacilityLmisReport(Request $request)
    {
        $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer',
            'facility_id' => 'required|exists:facilities,id',
            'rejection_reason' => 'nullable|string|max:2000',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $reportPeriod = sprintf('%04d-%02d', $request->year, $request->month);
                $report = FacilityMonthlyReport::where('facility_id', $request->facility_id)
                    ->where('report_period', $reportPeriod)
                    ->whereIn('status', ['submitted', 'reviewed'])
                    ->firstOrFail();

                $report->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'rejected_by' => auth()->id(),
                    'comments' => $request->rejection_reason ?: $report->comments,
                ]);

                return response()->json(['success' => true, 'message' => 'Report rejected.']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to reject facility LMIS report', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate facility LMIS report from movements for one facility and period.
     * Used by HTTP action and by the scheduled command. Returns data array or ['skipped' => true, 'reason' => ...].
     *
     * @return array{created_count: int, updated_count: int, total_products: int, movements_processed: int, report_id: int}|array{skipped: true, reason: string}
     */
    public function generateFacilityMonthlyReportForFacility(int $facilityId, int $year, int $month): array
    {
        $reportPeriod = sprintf('%04d-%02d', $year, $month);

        $existingReport = FacilityMonthlyReport::where('facility_id', $facilityId)
            ->where('report_period', $reportPeriod)
            ->first();

        if ($existingReport && $existingReport->status !== 'draft') {
            return ['skipped' => true, 'reason' => 'Report already exists and is not draft.'];
        }

        return DB::transaction(function () use ($facilityId, $year, $month, $reportPeriod) {
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();
            $previousMonth = $startDate->copy()->subMonth();

            $monthlyReport = FacilityMonthlyReport::firstOrCreate([
                'facility_id' => $facilityId,
                'report_period' => $reportPeriod,
            ], [
                'status' => 'draft',
            ]);

            $facility = Facility::findOrFail($facilityId);

            // Opening balances from previous month's closing balance (align with facilities LMIS)
            $previousReportItems = FacilityMonthlyReportItem::whereHas('report', function ($q) use ($facilityId, $previousMonth) {
                $q->where('facility_id', $facilityId)
                    ->where('report_period', $previousMonth->format('Y-m'));
            })->get()->keyBy('product_id');

            // Movements for the month grouped by product (align with facilities)
            $movements = FacilityInventoryMovement::where('facility_id', $facilityId)
                ->whereBetween('movement_date', [$startDate, $endDate])
                ->with('product')
                ->get()
                ->groupBy('product_id');

            $createdCount = 0;
            $updatedCount = 0;

            // 1. Process products with movements
            foreach ($movements as $productId => $productMovements) {
                $openingBalance = isset($previousReportItems[$productId])
                    ? (float) $previousReportItems[$productId]->closing_balance
                    : 0;
                $stockReceived = (float) $productMovements->where('movement_type', 'facility_received')->sum('facility_received_quantity');
                $stockIssued = (float) $productMovements->where('movement_type', 'facility_issued')->sum('facility_issued_quantity');
                $closingBalance = $openingBalance + $stockReceived - $stockIssued;
                $reportData = [
                    'product_id' => $productId,
                    'opening_balance' => $openingBalance,
                    'stock_received' => $stockReceived,
                    'stock_issued' => $stockIssued,
                    'positive_adjustments' => 0,
                    'negative_adjustments' => 0,
                    'closing_balance' => $closingBalance,
                    'stockout_days' => 0,
                ];
                $existingItem = $monthlyReport->items()->where('product_id', $productId)->first();
                if ($existingItem) {
                    $existingItem->update($reportData);
                    $updatedCount++;
                } else {
                    $monthlyReport->items()->create($reportData);
                    $createdCount++;
                }
            }

            // 2. Create empty items for eligible products with no movements (align with facilities)
            $eligibleProducts = $facility->eligibleProducts()->select('products.id', 'products.name')->get();
            $movementProductIds = $movements->keys()->toArray();

            foreach ($eligibleProducts as $product) {
                if (! in_array($product->id, $movementProductIds)) {
                    $openingBalance = isset($previousReportItems[$product->id])
                        ? (float) $previousReportItems[$product->id]->closing_balance
                        : 0;
                    $reportData = [
                        'product_id' => $product->id,
                        'opening_balance' => $openingBalance,
                        'stock_received' => 0,
                        'stock_issued' => 0,
                        'positive_adjustments' => 0,
                        'negative_adjustments' => 0,
                        'closing_balance' => $openingBalance,
                        'stockout_days' => 0,
                    ];
                    $existingItem = $monthlyReport->items()->where('product_id', $product->id)->first();
                    if ($existingItem) {
                        $existingItem->update($reportData);
                        $updatedCount++;
                    } else {
                        $monthlyReport->items()->create($reportData);
                        $createdCount++;
                    }
                }
            }

            return [
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'total_products' => $movements->count() + $eligibleProducts->filter(fn ($p) => ! in_array($p->id, $movementProductIds))->count(),
                'movements_processed' => $movements->count(),
                'report_id' => $monthlyReport->id,
            ];
        });
    }

    /**
     * Generate facility LMIS report from movements (HTTP).
     */
    public function generateFacilityLmisReportFromMovements(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'required|integer|min:1|max:12',
            'facility_id' => 'required|exists:facilities,id',
        ]);

        try {
            $result = $this->generateFacilityMonthlyReportForFacility(
                (int) $request->facility_id,
                (int) $request->year,
                (int) $request->month
            );
            if (!empty($result['skipped'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['reason'] ?? 'A report for this period already exists and cannot be regenerated.',
                ], 422);
            }
            return response()->json([
                'success' => true,
                'message' => 'LMIS report generated successfully from facility movements',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate facility LMIS report from movements', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate LMIS report: ' . $e->getMessage(),
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
    
}
