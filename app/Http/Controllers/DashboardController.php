<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Facility;
use Illuminate\Support\Facades\DB;
use App\Models\PackingList;
use App\Models\PurchaseOrder;
use App\Models\BackOrder;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\InventoryReport;
use App\Models\Category;
use App\Models\Asset;
use App\Models\AssetItem;
use App\Models\AssetHistory;
use App\Models\InventoryItem;
use App\Models\IssueQuantityReport;
use App\Models\FacilityMonthlyReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Check if user has ONLY asset permissions - show asset dashboard
        if ($this->hasOnlyAssetPermissions($user)) {
            return $this->assetDashboard($request);
        }
        
        // Otherwise, show the regular dashboard
        return $this->regularDashboard($request);
    }
    
    /**
     * Check if user has ONLY asset permissions (and no other permissions)
     */
    private function hasOnlyAssetPermissions($user)
    {
        // Get all user permissions
        $permissions = $user->permissions->pluck('name')->toArray();
        
        // Check if user has any non-asset permissions
        $hasNonAssetPermissions = collect($permissions)->some(function($permission) {
            return !str_starts_with($permission, 'asset-');
        });
        
        // Check if user has any asset permissions
        $hasAssetPermissions = collect($permissions)->some(function($permission) {
            return str_starts_with($permission, 'asset-');
        });
        
        // If user has asset permissions but no other permissions, show asset dashboard
        return $hasAssetPermissions && !$hasNonAssetPermissions;
    }
    
    /**
     * Show the asset dashboard
     */
    private function assetDashboard(Request $request)
    {
        $user = auth()->user();
        
        // Check if user has any asset permissions
        if (!$user->hasPermission('asset-view') && 
            !$user->hasPermission('asset-manage')) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to access the asset dashboard.');
        }

        // Get organization filter
        $organizationFilter = $user->organization;

        // Get asset statistics
        $assetQuery = Asset::query();
        if ($organizationFilter) {
            $assetQuery->where('organization', $organizationFilter);
        }

        $totalAssets = $assetQuery->count();
        $pendingApproval = $assetQuery->whereNotNull('submitted_at')->whereNull('approved_at')->whereNull('rejected_at')->count();
        $approved = $assetQuery->whereNotNull('approved_at')->count();
        $rejected = $assetQuery->whereNotNull('rejected_at')->count();

        // Get asset items statistics
        $assetItemsQuery = AssetItem::query();
        if ($organizationFilter) {
            $assetItemsQuery->whereHas('asset', function($query) use ($organizationFilter) {
                $query->where('organization', $organizationFilter);
            });
        }

        $inUse = $assetItemsQuery->where('status', 'in_use')->count();
        $maintenance = $assetItemsQuery->where('status', 'maintenance')->count();
        $totalValue = $assetItemsQuery->sum('original_value');

        // Get recent assets
        $recentAssets = $assetQuery->with(['assetItems'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($asset) {
                $firstAssetItem = $asset->assetItems->first();
                return [
                    'id' => $asset->id,
                    'asset_number' => $asset->asset_number,
                    'asset_name' => $firstAssetItem->asset_name ?? 'Unnamed Asset',
                    'asset_tag' => $firstAssetItem->asset_tag ?? $asset->asset_number,
                    'status' => $firstAssetItem->status ?? 'unknown',
                    'created_at' => $asset->created_at
                ];
            });

        // Get category breakdown
        $categoryBreakdown = AssetItem::query()
            ->when($organizationFilter, function($query) use ($organizationFilter) {
                $query->whereHas('asset', function($q) use ($organizationFilter) {
                    $q->where('organization', $organizationFilter);
                });
            })
            ->with('category')
            ->get()
            ->groupBy('category.name')
            ->map(function($items) {
                return $items->count();
            })
            ->toArray();

        // Get status breakdown
        $statusBreakdown = $assetItemsQuery->get()
            ->groupBy('status')
            ->map(function($items) {
                return $items->count();
            })
            ->toArray();

        // Get recent activity (from asset history)
        $recentActivity = AssetHistory::query()
            ->when($organizationFilter, function($query) use ($organizationFilter) {
                $query->whereHas('assetItem.asset', function($q) use ($organizationFilter) {
                    $q->where('organization', $organizationFilter);
                });
            })
            ->with(['assetItem.asset', 'performer'])
            ->orderBy('performed_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($history) {
                return [
                    'id' => $history->id,
                    'description' => $history->action . ' - ' . ($history->assetItem->asset->asset_number ?? 'Unknown Asset'),
                    'created_at' => $history->performed_at,
                    'performer' => $history->performer->name ?? 'System'
                ];
            });

        // Get asset categories for the chart
        $assetCategories = AssetItem::query()
            ->when($organizationFilter, function($query) use ($organizationFilter) {
                $query->whereHas('asset', function($q) use ($organizationFilter) {
                    $q->where('organization', $organizationFilter);
                });
            })
            ->with('category')
            ->get()
            ->groupBy('category.name')
            ->map(function($items, $categoryName) {
                return [
                    'name' => $categoryName ?: 'Uncategorized',
                    'count' => $items->count()
                ];
            })
            ->values()
            ->toArray();

        // Get asset status data for the chart (filtered by organization)
        $assetStatusData = AssetItem::query()
            ->when($organizationFilter, function($query) use ($organizationFilter) {
                $query->whereHas('asset', function($q) use ($organizationFilter) {
                    $q->where('organization', $organizationFilter);
                });
            })
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Format status data with all possible statuses
        $formattedStatusData = [
            'In Use' => $assetStatusData['in_use'] ?? 0,
            'Functioning' => $assetStatusData['functioning'] ?? 0,
            'Not functioning' => $assetStatusData['not_functioning'] ?? 0,
            'Needs Maintenance' => $assetStatusData['maintenance'] ?? 0,
            'Pending Approval' => $assetStatusData['pending_approval'] ?? 0,
            'Retired' => $assetStatusData['retired'] ?? 0,
            'Disposed' => $assetStatusData['disposed'] ?? 0,
        ];

        $functioningCount = ($assetStatusData['functioning'] ?? 0) + ($assetStatusData['in_use'] ?? 0);
        $notFunctioningCount = $assetStatusData['not_functioning'] ?? 0;

        $assetStats = [
            'total_assets' => $totalAssets,
            'functioning_assets' => $functioningCount,
            'not_functioning_assets' => $notFunctioningCount,
            'inactive_assets' => $notFunctioningCount, // backward compat
            'pending_approval' => $pendingApproval,
            'disposed_assets' => $assetItemsQuery->where('status', 'disposed')->count(),
            'asset_categories' => $assetCategories,
            'asset_status_data' => $formattedStatusData
        ];

        return Inertia::render('Assets/AssetDashboard', [
            'assetStats' => $assetStats,
            'recentAssets' => $recentAssets,
            'recentActivity' => $recentActivity,
            'userPermissions' => $user->permissions->pluck('name')->toArray()
        ]);
    }
    
    /**
     * Show the regular dashboard
     */
    private function regularDashboard(Request $request)
    {
        // Get warehouse count and add it first
        $warehouseCount = Warehouse::count();
        $warehouseData = collect([
            [
                'label' => 'WH',
                'fullName' => 'Warehouses',
                'value' => $warehouseCount,
                'color' => 'blue',
            ]
        ]);

        // Get distinct facility types and their counts (all types)
        $facilityTypes = Facility::select('facility_type', DB::raw('count(*) as count'))
            ->whereNotNull('facility_type')
            ->where('facility_type', '!=', '')
            ->groupBy('facility_type')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($type) {
                return [
                    'label' => $this->getAbbreviatedName($type->facility_type),
                    'fullName' => $type->facility_type,
                    'value' => $type->count,
                    'color' => $this->getFacilityTypeColor($type->facility_type),
                ];
            });

        // Add Mobile Team manually at the end
        $facilityTypes->push([
            'label' => 'MT',
            'fullName' => 'Mobile Team',
            'value' => 5,
            'color' => 'lightblue',
        ]);

        // Combine warehouse data first, then facility types
        $facilityTypes = $warehouseData->concat($facilityTypes);
        
        $filter = $request->input('order_filter', 'PO'); // default to PO

        $orderCounts = [
            'PKL' => PackingList::count(),
            'PO'  => PurchaseOrder::where('status', 'approved')->count(),
            'BO'  => BackOrder::count(),
        ];

        // Product category counts - dynamically read from database
        $productCategoryCounts = [];
        $categories = Category::withCount('products')->get();
        foreach ($categories as $category) {
            $productCategoryCounts[$category->name] = $category->products_count;
        }

        // Transfer received count
        $transferReceivedCount = Transfer::where('status', 'received')->count();

        // User and Warehouse counts
        $userCount = User::count();
        $warehouseCount = Warehouse::count();

        // Order status statistics
        $orderStats = [
            'pending' => Order::where('status', 'pending')->count(),
            'reviewed' => Order::where('status', 'reviewed')->count(),
            'approved' => Order::where('status', 'approved')->count(),
            'in_process' => Order::where('status', 'in_process')->count(),
            'dispatched' => Order::where('status', 'dispatched')->count(),
            'received' => Order::where('status', 'received')->count(),
            'rejected' => Order::where('status', 'rejected')->count(),
        ];

        // Total cost of approved purchase orders
        $totalApprovedPOCost = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.status', 'approved')
            ->sum('purchase_order_items.total_cost');

        // Get all suppliers first
        $suppliers = Supplier::select('id', 'name')->get();
        
        // Fulfillment percentage for packing list items by supplier
        $fulfillmentData = collect();
        
        foreach ($suppliers as $supplier) {
            $supplierFulfillment = DB::table('packing_list_items as pli')
                ->join('packing_lists as pl', 'pli.packing_list_id', '=', 'pl.id')
                ->join('purchase_order_items as poi', 'pli.po_item_id', '=', 'poi.id')
                ->join('purchase_orders as po', 'poi.purchase_order_id', '=', 'po.id')
                ->where('po.supplier_id', $supplier->id)
                ->select(
                    DB::raw('SUM(poi.quantity) as total_ordered'),
                    DB::raw('SUM(pli.quantity) as total_received')
                )
                ->first();
            
            $fulfillmentPercentage = 0;
            if ($supplierFulfillment && $supplierFulfillment->total_ordered > 0) {
                $fulfillmentPercentage = round(($supplierFulfillment->total_received / $supplierFulfillment->total_ordered) * 100, 2);
            }
            
            $fulfillmentData->push((object) [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'total_ordered' => $supplierFulfillment ? $supplierFulfillment->total_ordered : 0,
                'total_received' => $supplierFulfillment ? $supplierFulfillment->total_received : 0,
                'fulfillment_percentage' => $fulfillmentPercentage
            ]);
        }

        // Calculate overall average fulfillment
        $overallFulfillment = $fulfillmentData->avg('fulfillment_percentage') ?? 0;

        // Check if we have any data in the related tables
        $packingListItemsCount = DB::table('packing_list_items')->count();
        $purchaseOrderItemsCount = DB::table('purchase_order_items')->count();
        $packingListsCount = DB::table('packing_lists')->count();
        $purchaseOrdersCount = DB::table('purchase_orders')->count();

        $ordersDelayedCount = \App\Models\Order::whereNotNull('order_date')
            ->whereNotNull('expected_date')
            ->whereRaw('order_date < expected_date')
            ->count();

        // Issued Quantity Data for Warehouse Tab
        $issuedMonths = IssueQuantityReport::orderBy('month_year', 'desc')->pluck('month_year')->unique()->toArray();
        $selectedIssuedMonth = request('issued_month', \Carbon\Carbon::now()->format('Y-m'));
        $issuedReport = IssueQuantityReport::where('month_year', $selectedIssuedMonth)
            ->with(['items.product'])
            ->first();
        $issuedData = [];
        if ($issuedReport) {
            foreach ($issuedReport->items as $item) {
                $issuedData[] = [
                    'product' => $item->product ? $item->product->name : 'Unknown',
                    'quantity' => $item->quantity,
                ];
            }
        }

        // Warehouse Tab Data Type Selection
        $warehouseDataType = request('warehouse_data_type', 'qty_issued');
        $issuedMonths = IssueQuantityReport::orderBy('month_year', 'desc')->pluck('month_year')->unique()->toArray();
        $selectedIssuedMonth = request('issued_month', Carbon::now()->format('Y-m'));
        $warehouseChartData = [];
        if ($warehouseDataType === 'qty_issued') {
            $issuedReport = IssueQuantityReport::where('month_year', $selectedIssuedMonth)
                ->with(['items.product'])
                ->first();
            if ($issuedReport) {
                foreach ($issuedReport->items as $item) {
                    $warehouseChartData[] = [
                        'product' => $item->product ? $item->product->name : 'Unknown',
                        'quantity' => $item->quantity,
                    ];
                }
            }
        }

        $loadSuppliers = Supplier::pluck('name')->toArray();

        // Inventory statistics
        $statusCounts = [
            'in_stock' => 0,
            'low_stock' => 0,
            'out_of_stock' => 0,
        ];
        $inventories = \App\Models\Inventory::with('items')->get();
        foreach ($inventories as $inventory) {
            $reorderLevel = $inventory->reorder_level ?? 0;
            foreach ($inventory->items ?? [] as $item) {
                $qty = $item->quantity;
                if ($qty == 0) {
                    $statusCounts['out_of_stock']++;
                } elseif ($qty <= $reorderLevel) {
                    $statusCounts['low_stock']++;
                } else {
                    $statusCounts['in_stock']++;
                }
            }
        }

        // Expired statistics
        $now = Carbon::now();
        $sixMonthsFromNow = $now->copy()->addMonths(6);
        $oneYearFromNow = $now->copy()->addYear();

        $expiredCount = \App\Models\InventoryItem::where('quantity', '>', 0)
            ->where('expiry_date', '<', $now)
            ->count();
        $expiring6MonthsCount = \App\Models\InventoryItem::where('quantity', '>', 0)
            ->where('expiry_date', '>=', $now)
            ->where('expiry_date', '<=', $sixMonthsFromNow)
            ->count();
        $expiring1YearCount = \App\Models\InventoryItem::where('quantity', '>', 0)
            ->where('expiry_date', '>=', $now)
            ->where('expiry_date', '<=', $oneYearFromNow)
            ->count();

        $expiredStats = [
            'expired' => $expiredCount,
            'expiring_within_6_months' => $expiring6MonthsCount,
            'expiring_within_1_year' => $expiring1YearCount,
        ];

        // Asset statistics by category
        $assetStats = $this->getAssetStatistics();



        $responseData = [
            'dashboardData' => [
                'summary' => $facilityTypes,
                'order_stats' => [],
                'tasks' => $this->generateDashboardTasks(),

                'recommended_actions' => [],
                'product_status' => []
            ],
            'loadSuppliers' => $loadSuppliers,
            'orderCard' => [
                'filter' => $filter,
                'counts' => $orderCounts,
            ],
            'productCategoryCard' => $productCategoryCounts,
            'transferReceivedCard' => $transferReceivedCount,
            'userCountCard' => $userCount,
            'warehouseCountCard' => $warehouseCount,
            'tracertableData' => [
                'summary' => [
                    'totalItems' => 0,
                    'warehouseItems' => 0,
                    'facilityItems' => 0,
                    'totalQuantity' => 0
                ],
                'warehouseItems' => [],
                'facilityItems' => [],
                'facilities' => [],
                'warehouseChartData' => [
                    'labels' => ['In Stock', 'Low Stock', 'Out of Stock'],
                    'data' => [0, 0, 0]
                ],
                'facilityChartData' => [
                    'labels' => [],
                    'data' => []
                ]
            ],
            'orderStats' => $orderStats,
            'totalApprovedPOCost' => $totalApprovedPOCost,
            'fulfillmentData' => $fulfillmentData,
            'overallFulfillment' => $overallFulfillment,
            'ordersDelayedCount' => $ordersDelayedCount,
            'issuedMonths' => $issuedMonths,
            'selectedIssuedMonth' => $selectedIssuedMonth,
            'warehouseDataType' => $warehouseDataType,
            'warehouseChartData' => $warehouseChartData,
            'issuedData' => $issuedData,
            'inventoryStatusCounts' => collect($statusCounts)->map(fn($count, $status) => ['status' => $status, 'count' => $count])->values(),
            'expiredStats' => $expiredStats,
            'assetStats' => $assetStats['categories'],
            'assetStatusStats' => $assetStats['statuses'],
            'warehouses' => Warehouse::select('id', 'name')->orderBy('name')->get(),
        ];



        return Inertia::render('Dashboard', $responseData);
    }

    public function warehouseTracertItems(Request $request)
    {
        try {
            $type = $request->type ?? 'beginning_balance';
            $month = $request->month ?? now()->subMonth()->format('Y-m');
            $warehouseId = $request->warehouse_id;
            
            // Validate the type is one of the allowed columns
            $allowedTypes = ['beginning_balance', 'received_quantity', 'issued_quantity', 'closing_balance'];
            if (!in_array($type, $allowedTypes)) {
                $type = 'beginning_balance';
            }
            
            // Get the inventory report for the specified month
            $inventoryReport = InventoryReport::where('month_year', $month)->first();
            
            // Get all warehouse products with their categories
            $warehouseProducts = Product::with('category')
                ->whereRaw('JSON_CONTAINS(tracert_type, ?)', ['"Warehouse"'])
                ->orWhere('tracert_type', 'like', '%Warehouse%')
                ->select('id', 'name', 'productID', 'tracert_type', 'category_id')
                ->get();

            if ($warehouseProducts->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "No warehouse products found",
                    'chartData' => $this->getEmptyChartData(),
                    'summary' => $this->getEmptySummary()
                ], 404);
            }

            // Create a collection to hold the items with their inventory data
            $items = collect();
            
            foreach ($warehouseProducts as $product) {
                // Find the inventory report item for this product
                $inventoryItem = null;
                if ($inventoryReport) {
                    $query = $inventoryReport->items()->where('product_id', $product->id);
                    
                    // Apply warehouse filter if provided
                    if ($warehouseId) {
                        $query->where('warehouse_id', $warehouseId);
                    }
                    
                    $inventoryItem = $query->first();
                }
                
                // Get category name from the product's category relationship
                $categoryName = $product->category ? $product->category->name : 'Uncategorized';

                // Create a mock item with the product data and inventory values (or zeros if no data)
                $mockItem = (object) [
                    'id' => $inventoryItem ? $inventoryItem->id : 'mock_' . $product->id,
                    'product' => $product,
                    'category' => $product->category,
                    'category_name' => $categoryName,
                    'beginning_balance' => $inventoryItem ? $inventoryItem->beginning_balance : 0,
                    'received_quantity' => $inventoryItem ? $inventoryItem->received_quantity : 0,
                    'issued_quantity' => $inventoryItem ? $inventoryItem->issued_quantity : 0,
                    'closing_balance' => $inventoryItem ? $inventoryItem->closing_balance : 0,
                ];
                
                $items->push($mockItem);
            }
            
            // Sort by the selected type in descending order
            $items = $items->sortByDesc($type);

            if ($items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "No warehouse products found",
                    'chartData' => $this->getEmptyChartData(),
                    'summary' => $this->getEmptySummary()
                ], 404);
            }

            // Process data for charts
            $chartData = $this->processChartData($items, $type);
            $summary = $this->processSummaryData($items, $type);

            return response()->json([
                'success' => true,
                'month' => $month,
                'type' => $type,
                'chartData' => $chartData,
                'summary' => $summary,
                'items' => $items->map(function ($item) use ($type) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->product->name,
                        'product_id' => $item->product->productID,
                        'category_name' => $item->category_name,
                        'value' => $item->{$type},
                        'beginning_balance' => $item->beginning_balance,
                        'received_quantity' => $item->received_quantity,
                        'issued_quantity' => $item->issued_quantity,
                        'closing_balance' => $item->closing_balance,
                    ];
                })
            ], 200);

        } catch (\Throwable $th) {            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouse data: ' . $th->getMessage(),
                'chartData' => $this->getEmptyChartData(),
                'summary' => $this->getEmptySummary()
            ], 500);
        }        
    }

    public function facilityTracertItems(Request $request)
    {
        try {
            $type = $request->type ?? 'opening_balance';
            $month = $request->month ?? now()->subMonth()->format('Y-m');
            $facilityId = $request->facility_id ?? null;
            
            // Note: Only products with 'Facility' in their tracert_type array will be included
            
            // Validate the type is one of the allowed columns
            // opening_balance = Beginning Balance, stock_received = QTY Received, 
            // stock_issued = Issued Quantity (Monthly Consumption), closing_balance = Closing Balance (Calculated)
            // positive_adjustments = Positive Adjustments, negative_adjustments = Negative Adjustments
            $allowedTypes = ['opening_balance', 'stock_received', 'stock_issued', 'closing_balance', 'positive_adjustments', 'negative_adjustments'];
            if (!in_array($type, $allowedTypes)) {
                $type = 'opening_balance';
            }
            
            // Get facilities list for frontend
            $facilities = Facility::select('id', 'name', 'facility_type')
                ->orderBy('name')
                ->get();
            
            // Build query for facility monthly reports
            $query = FacilityMonthlyReport::where('report_period', $month)
                ->with(['facility', 'items.product.category']);
                
            // Filter by facility if specified, otherwise get all facilities
            if ($facilityId) {
                $query->where('facility_id', $facilityId);
                $facilityReports = [$query->first()];
                $selectedFacilityName = $facilityReports[0]->facility->name ?? 'Unknown Facility';
            } else {
                $facilityReports = $query->get()->toArray();
                $selectedFacilityName = 'All Facilities';
            }
            
            // Filter out null reports and check if we have any data
            $facilityReports = array_filter($facilityReports, function($report) {
                return $report && !empty($report['items']);
            });
                
            if (empty($facilityReports)) {
                return response()->json([
                    'success' => false,
                    'message' => "No facility reports found for {$month}",
                    'chartData' => $this->getEmptyChartData(),
                    'summary' => $this->getEmptySummary(),
                    'facilities' => $facilities
                ], 404);
            }
            
            // Create a collection to hold all items from all facilities
            $items = collect();
            
            foreach ($facilityReports as $facilityReport) {
                if (!$facilityReport || empty($facilityReport['items'])) continue;
                
                foreach ($facilityReport['items'] as $reportItem) {
                    // Convert array to object if needed
                    if (is_array($reportItem)) {
                        $reportItem = (object) $reportItem;
                        if (isset($reportItem->product) && is_array($reportItem->product)) {
                            $reportItem->product = (object) $reportItem->product;
                            if (isset($reportItem->product->category) && is_array($reportItem->product->category)) {
                                $reportItem->product->category = (object) $reportItem->product->category;
                            }
                        }
                    }
                    
                    if (!$reportItem->product) continue;
                    
                    // Only include products that are traceable for facilities
                    $tracertType = $reportItem->product->tracert_type ?? '';
                    $isFacilityTraceable = false;
                    
                    if (is_string($tracertType)) {
                        // Handle string format
                        $isFacilityTraceable = str_contains($tracertType, 'Facility');
                    } elseif (is_array($tracertType)) {
                        // Handle array format
                        $isFacilityTraceable = in_array('Facility', $tracertType);
                    } else {
                        // Handle JSON string format
                        $decoded = json_decode($tracertType, true);
                        if (is_array($decoded)) {
                            $isFacilityTraceable = in_array('Facility', $decoded);
                        }
                    }
                    
                    if (!$isFacilityTraceable) continue;
                    
                    // Get category name from the product's category relationship
                    $categoryName = $reportItem->product->category ? $reportItem->product->category->name : 'Uncategorized';

                    // Calculate closing balance using LMIS formula: 
                    // Opening Balance + Stock Received - Stock Issued + Positive Adjustments - Negative Adjustments
                    $calculatedClosingBalance = ($reportItem->opening_balance ?? 0)
                                              + ($reportItem->stock_received ?? 0)
                                              - ($reportItem->stock_issued ?? 0)
                                              + ($reportItem->positive_adjustments ?? 0)
                                              - ($reportItem->negative_adjustments ?? 0);
                    
                    // Create a mock item with the report item data
                    $mockItem = (object) [
                        'id' => $reportItem->id,
                        'product' => $reportItem->product,
                        'category' => $reportItem->product->category,
                        'category_name' => $categoryName,
                        'opening_balance' => $reportItem->opening_balance ?? 0,
                        'stock_received' => $reportItem->stock_received ?? 0,
                        'stock_issued' => $reportItem->stock_issued ?? 0,
                        'positive_adjustments' => $reportItem->positive_adjustments ?? 0,
                        'negative_adjustments' => $reportItem->negative_adjustments ?? 0,
                        'closing_balance' => $calculatedClosingBalance, // Use calculated value
                        'stored_closing_balance' => $reportItem->closing_balance ?? 0, // Keep original for reference
                    ];
                    
                    $items->push($mockItem);
                }
            }
            
            // Sort by the selected type in descending order
            $items = $items->sortByDesc($type);

            if ($items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "No facility traceable items found for {$month}",
                    'chartData' => $this->getEmptyChartData(),
                    'summary' => $this->getEmptySummary(),
                    'facilities' => $facilities
                ], 404);
            }

            // Process data for charts
            $chartData = $this->processChartData($items, $type);
            $summary = $this->processSummaryData($items, $type);

            return response()->json([
                'success' => true,
                'month' => $month,
                'type' => $type,
                'facility' => $selectedFacilityName,
                'facilities' => $facilities,
                'chartData' => $chartData,
                'summary' => $summary,
                'items' => $items->map(function ($item) use ($type) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->product->name,
                        'product_id' => $item->product->productID,
                        'category_name' => $item->category_name,
                        'value' => $item->{$type},
                        'opening_balance' => $item->opening_balance,
                        'stock_received' => $item->stock_received,
                        'stock_issued' => $item->stock_issued,
                        'positive_adjustments' => $item->positive_adjustments,
                        'negative_adjustments' => $item->negative_adjustments,
                        'closing_balance' => $item->closing_balance, // Calculated value
                        'stored_closing_balance' => $item->stored_closing_balance, // Original stored value
                    ];
                })
            ], 200);

        } catch (\Throwable $th) {             
            // Try to get facilities list even on error
            $facilities = collect();
            try {
                $facilities = Facility::select('id', 'name', 'facility_type')
                    ->orderBy('name')
                    ->get();
            } catch (\Exception $e) {
                // If facilities can't be loaded, use empty collection
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch facility data: ' . $th->getMessage(),
                'chartData' => $this->getEmptyChartData(),
                'summary' => $this->getEmptySummary(),
                'facilities' => $facilities
            ], 500);
        }        
    }

    /**
     * Process data for chart visualization - chunk by 4 but organized by category
     */
    private function processChartData($items, $type)
    {
        if ($items->isEmpty()) {
            return [
                'charts' => [$this->getEmptyChartData()],
                'totalCharts' => 1
            ];
        }

        // Color palette for charts
        $colors = [
            ['bg' => 'rgba(59, 130, 246, 0.8)', 'border' => 'rgb(59, 130, 246)'], // Blue
            ['bg' => 'rgba(16, 185, 129, 0.8)', 'border' => 'rgb(16, 185, 129)'], // Green
            ['bg' => 'rgba(245, 158, 11, 0.8)', 'border' => 'rgb(245, 158, 11)'], // Yellow
            ['bg' => 'rgba(239, 68, 68, 0.8)', 'border' => 'rgb(239, 68, 68)'], // Red
            ['bg' => 'rgba(147, 51, 234, 0.8)', 'border' => 'rgb(147, 51, 234)'], // Purple
            ['bg' => 'rgba(236, 72, 153, 0.8)', 'border' => 'rgb(236, 72, 153)'], // Pink
            ['bg' => 'rgba(14, 165, 233, 0.8)', 'border' => 'rgb(14, 165, 233)'], // Sky
            ['bg' => 'rgba(34, 197, 94, 0.8)', 'border' => 'rgb(34, 197, 94)'], // Emerald
            ['bg' => 'rgba(168, 85, 247, 0.8)', 'border' => 'rgb(168, 85, 247)'], // Violet
            ['bg' => 'rgba(251, 191, 36, 0.8)', 'border' => 'rgb(251, 191, 36)'] // Amber
        ];

        // Group items by category and create separate charts for each category
        $itemsByCategory = $items->groupBy('category_name');
        $charts = [];
        $chartId = 1;
        
        foreach ($itemsByCategory as $categoryName => $categoryItems) {
            // Sort items within this category by the selected type (descending)
            $sortedCategoryItems = $categoryItems->sortByDesc($type);
            
            // Chunk items within this category by 4
            $categoryChunks = $sortedCategoryItems->chunk(4);
            
            foreach ($categoryChunks as $chunkIndex => $chunk) {
                $labels = [];
                $data = [];
                $backgroundColors = [];
                $borderColors = [];
                
                foreach ($chunk as $index => $item) {
                    // Truncate long product names for better chart display
                    $productName = strlen($item->product->name) > 20 
                        ? substr($item->product->name, 0, 20) . '...'
                        : $item->product->name;
                        
                    $labels[] = $productName;
                    $data[] = (float) $item->{$type};
                    
                    $colorIndex = $index % count($colors);
                    $backgroundColors[] = $colors[$colorIndex]['bg'];
                    $borderColors[] = $colors[$colorIndex]['border'];
                }

                $charts[] = [
                    'id' => $chartId++,
                    'category' => $categoryName,
                    'categoryDisplay' => $categoryName,
                    'labels' => $labels,
                    'data' => $data,
                    'backgroundColors' => $backgroundColors,
                    'borderColors' => $borderColors,
                    'total' => array_sum($data),
                    'count' => count($data)
                ];
            }
        }

        return [
            'charts' => $charts,
            'totalCharts' => count($charts),
            'totalItems' => $items->count()
        ];
    }

    /**
     * Process summary data
     */
    private function processSummaryData($items, $type)
    {
        if ($items->isEmpty()) {
            return $this->getEmptySummary();
        }

        $total = $items->sum($type);
        $average = $items->avg($type);
        $max = $items->max($type);
        $min = $items->where($type, '>', 0)->min($type);

        return [
            'total' => number_format($total),
            'average' => number_format($average, 2),
            'max' => number_format($max),
            'min' => number_format($min ?? 0),
            'count' => $items->count(),
            'type_label' => $this->getTypeLabel($type)
        ];
    }

    /**
     * Get empty chart data
     */
    private function getEmptyChartData()
    {
        return [
            'id' => 1,
            'category' => 'No Data',
            'categoryDisplay' => 'No Data Available',
            'labels' => ['No Data Available'],
            'data' => [0],
            'backgroundColors' => ['rgba(156, 163, 175, 0.8)'],
            'borderColors' => ['rgba(156, 163, 175, 1)'],
            'total' => 0,
            'count' => 0
        ];
    }

    /**
     * Get empty summary
     */
    private function getEmptySummary()
    {
        return [
            'total' => '0',
            'average' => '0',
            'max' => '0',
            'min' => '0',
            'count' => 0,
            'type_label' => 'No Data'
        ];
    }

    /**
     * Get human-readable type label
     */
    private function getTypeLabel($type)
    {
        $labels = [
            'beginning_balance' => 'Beginning Balance',
            'received_quantity' => 'Quantity Received',
            'issued_quantity' => 'Quantity Issued',
            'closing_balance' => 'Closing Balance'
        ];

        return $labels[$type] ?? 'Unknown';
    }

    private function getFacilityTypeColor($facilityType)
    {
        $colors = [
            'Regional Hospital' => 'red',
            'District Hospital' => 'orange',
            'Health Center' => 'blue',
            'Primary Health Unit' => 'green',
            'regional hospital' => 'red',
            'district hospital' => 'orange',
            'primary health unit' => 'green'
        ];

        return $colors[$facilityType] ?? 'gray';
    }

    private function getAbbreviatedName($facilityType)
    {
        $abbreviations = [
            'Regional Hospital' => 'RH',
            'District Hospital' => 'DH',
            'Health Center' => 'HC',
            'Primary Health Unit' => 'PHU',
            'regional hospital' => 'RH',
            'district hospital' => 'DH',
            'primary health unit' => 'PHU'
        ];

        return $abbreviations[$facilityType] ?? $facilityType;
    }

    private function getAssetStatistics()
    {
        // Define the main categories we want to track
        $mainCategories = ['Furniture', 'IT', 'Medical equipment', 'Vehicles'];
        
        // Get all assets with their asset items and categories
        $assets = \App\Models\Asset::with(['assetItems.category'])->get();
        
        // Initialize counts
        $categoryCounts = [
            'Furniture' => 0,
            'IT' => 0,
            'Medical equipment' => 0,
            'Vehicles' => 0,
            'Others' => 0
        ];
        
        // Initialize status counts
        $statusCounts = [
            'In Use' => 0,
            'Maintenance' => 0,
            'Disposed' => 0
        ];
        
        // Count assets by category and status
        foreach ($assets as $asset) {
            // Count by asset items categories
            foreach ($asset->assetItems as $assetItem) {
                $categoryName = $assetItem->category ? $assetItem->category->name : 'Unknown';
                
                // Check if it matches any of our main categories (case-insensitive)
                $matched = false;
                foreach ($mainCategories as $mainCategory) {
                    if (stripos($categoryName, $mainCategory) !== false) {
                        $categoryCounts[$mainCategory]++;
                        $matched = true;
                        break;
                    }
                }
                
                // If no match found, count as "Others"
                if (!$matched) {
                    $categoryCounts['Others']++;
                }
            }
            
            // Count by status (using asset items status)
            foreach ($asset->assetItems as $assetItem) {
                switch ($assetItem->status) {
                    case 'in_use':
                    case 'active':
                        $statusCounts['In Use']++;
                        break;
                    case 'maintenance':
                        $statusCounts['Maintenance']++;
                        break;
                    case 'disposed':
                    case 'retired':
                        $statusCounts['Disposed']++;
                        break;
                }
            }
        }
        
        return [
            'categories' => $categoryCounts,
            'statuses' => $statusCounts
        ];
    }

    /**
     * Process categorized chart data
     */
    private function processCategorizedChartData($categoryTotals, $type)
    {
        if ($categoryTotals->isEmpty()) {
            return $this->getEmptyChartData();
        }

        // Create a main chart for category totals
        $labels = $categoryTotals->pluck('category_name')->toArray();
        $data = $categoryTotals->pluck('total_value')->toArray();
        
        // Generate colors for each category
        $colors = $this->generateCategoryColors(count($labels));
        
        $mainChart = [
            'id' => 'main',
            'labels' => $labels,
            'data' => $data,
            'backgroundColors' => $colors['background'],
            'borderColors' => $colors['border'],
            'total' => array_sum($data),
            'count' => $categoryTotals->sum('product_count')
        ];

        // Create individual charts for each category
        $categoryCharts = [];
        foreach ($categoryTotals as $index => $category) {
            $categoryItems = $category['items'];
            
            if ($categoryItems->isNotEmpty()) {
                // Take top 10 items for each category chart
                $topItems = $categoryItems->take(10);
                
                $itemLabels = $topItems->pluck('product_name')->toArray();
                $itemData = $topItems->pluck($type)->toArray();
                
                $categoryCharts[] = [
                    'id' => 'category_' . $index,
                    'labels' => $itemLabels,
                    'data' => $itemData,
                    'backgroundColors' => array_fill(0, count($itemData), $colors['background'][(int)$index % count($colors['background'])]),
                    'borderColors' => array_fill(0, count($itemData), $colors['border'][(int)$index % count($colors['border'])]),
                    'total' => $category['total_value'],
                    'count' => $category['product_count'],
                    'category_name' => $category['category_name']
                ];
            }
        }

        return [
            'charts' => array_merge([$mainChart], $categoryCharts),
            'totalCharts' => count($categoryCharts) + 1
        ];
    }

    /**
     * Process categorized summary data
     */
    private function processCategorizedSummaryData($categoryTotals, $type)
    {
        if ($categoryTotals->isEmpty()) {
            return $this->getEmptySummary();
        }

        $total = $categoryTotals->sum('total_value');
        $average = $categoryTotals->avg('total_value');
        $max = $categoryTotals->max('total_value');
        $min = $categoryTotals->where('total_value', '>', 0)->min('total_value');

        return [
            'total' => number_format($total),
            'average' => number_format($average, 2),
            'max' => number_format($max),
            'min' => number_format($min ?? 0),
            'count' => $categoryTotals->sum('product_count'),
            'category_count' => $categoryTotals->count(),
            'type_label' => $this->getTypeLabel($type)
        ];
    }

    /**
     * Generate colors for categories
     */
    private function generateCategoryColors($count)
    {
        $baseColors = [
            ['background' => 'rgba(59, 130, 246, 0.8)', 'border' => 'rgba(59, 130, 246, 1)'], // Blue
            ['background' => 'rgba(16, 185, 129, 0.8)', 'border' => 'rgba(16, 185, 129, 1)'], // Green
            ['background' => 'rgba(245, 158, 11, 0.8)', 'border' => 'rgba(245, 158, 11, 1)'], // Yellow
            ['background' => 'rgba(239, 68, 68, 0.8)', 'border' => 'rgba(239, 68, 68, 1)'], // Red
            ['background' => 'rgba(139, 92, 246, 0.8)', 'border' => 'rgba(139, 92, 246, 1)'], // Purple
            ['background' => 'rgba(236, 72, 153, 0.8)', 'border' => 'rgba(236, 72, 153, 1)'], // Pink
            ['background' => 'rgba(14, 165, 233, 0.8)', 'border' => 'rgba(14, 165, 233, 1)'], // Sky
            ['background' => 'rgba(34, 197, 94, 0.8)', 'border' => 'rgba(34, 197, 94, 1)'], // Emerald
        ];

        $backgroundColors = [];
        $borderColors = [];

        for ($i = 0; $i < $count; $i++) {
            $colorIndex = $i % count($baseColors);
            $backgroundColors[] = $baseColors[$colorIndex]['background'];
            $borderColors[] = $baseColors[$colorIndex]['border'];
        }

        return [
            'background' => $backgroundColors,
            'border' => $borderColors
        ];
    }

    private function generateDashboardTasks()
    {
        $tasks = [];
        
        // 1. ORDER WORKFLOW TASKS - Next Stage Actions
        
        // Orders waiting for review (pending -> reviewed)
        $ordersPendingReview = Order::where('status', 'pending')->count();
        if ($ordersPendingReview > 0) {
            $tasks[] = [
                'id' => 'orders_pending_review',
                'type' => 'workflow',
                'title' => 'Orders Awaiting Review',
                'description' => "{$ordersPendingReview} orders ready to move to review stage",
                'count' => $ordersPendingReview,
                'priority' => 'high',
                'icon' => 'clipboard-check',
                'color' => 'yellow',
                'route' => route('orders.index', ['status' => 'pending']),
                'category' => 'Orders',
                'current_stage' => 'Pending',
                'next_stage' => 'Review'
            ];
        }

        // Orders waiting for approval (reviewed -> approved)
        $ordersPendingApproval = Order::where('status', 'reviewed')->count();
        if ($ordersPendingApproval > 0) {
            $tasks[] = [
                'id' => 'orders_pending_approval',
                'type' => 'workflow',
                'title' => 'Orders Ready for Approval',
                'description' => "{$ordersPendingApproval} reviewed orders waiting for final approval",
                'count' => $ordersPendingApproval,
                'priority' => 'high',
                'icon' => 'check-circle',
                'color' => 'green',
                'route' => route('orders.index', ['status' => 'reviewed']),
                'category' => 'Orders',
                'current_stage' => 'Reviewed',
                'next_stage' => 'Approve'
            ];
        }

        // Orders ready for processing (approved -> in_process)
        $ordersReadyForProcessing = Order::where('status', 'approved')->count();
        if ($ordersReadyForProcessing > 0) {
            $tasks[] = [
                'id' => 'orders_ready_processing',
                'type' => 'workflow',
                'title' => 'Orders Ready for Processing',
                'description' => "{$ordersReadyForProcessing} approved orders ready to start processing",
                'count' => $ordersReadyForProcessing,
                'priority' => 'medium',
                'icon' => 'cog',
                'color' => 'blue',
                'route' => route('orders.index', ['status' => 'approved']),
                'category' => 'Orders',
                'current_stage' => 'Approved',
                'next_stage' => 'Process'
            ];
        }

        // Orders ready for dispatch (in_process -> dispatched)
        $ordersReadyForDispatch = Order::where('status', 'in_process')->count();
        if ($ordersReadyForDispatch > 0) {
            $tasks[] = [
                'id' => 'orders_ready_dispatch',
                'type' => 'workflow',
                'title' => 'Orders Ready for Dispatch',
                'description' => "{$ordersReadyForDispatch} processed orders ready for dispatch",
                'count' => $ordersReadyForDispatch,
                'priority' => 'medium',
                'icon' => 'truck',
                'color' => 'blue',
                'route' => route('orders.index', ['status' => 'in_process']),
                'category' => 'Orders',
                'current_stage' => 'In Process',
                'next_stage' => 'Dispatch'
            ];
        }

        // 2. ASSET WORKFLOW TASKS - Next Stage Actions
        
        // Assets waiting for review (pending_approval -> reviewed)
        $assetsPendingReview = \App\Models\Asset::pendingApproval()->count();
        if ($assetsPendingReview > 0) {
            $tasks[] = [
                'id' => 'assets_pending_review',
                'type' => 'workflow',
                'title' => 'Assets Awaiting Review',
                'description' => "{$assetsPendingReview} assets ready to move to review stage",
                'count' => $assetsPendingReview,
                'priority' => 'high',
                'icon' => 'cube',
                'color' => 'yellow',
                'route' => route('assets.approvals.index'),
                'category' => 'Assets',
                'current_stage' => 'Pending Approval',
                'next_stage' => 'Review'
            ];
        }

        // Assets waiting for approval (reviewed -> approved)
        $assetsPendingApproval = \App\Models\Asset::whereNotNull('reviewed_at')->whereNull('approved_at')->whereNull('rejected_at')->count();
        if ($assetsPendingApproval > 0) {
            $tasks[] = [
                'id' => 'assets_pending_approval',
                'type' => 'workflow',
                'title' => 'Assets Ready for Approval',
                'description' => "{$assetsPendingApproval} reviewed assets waiting for final approval",
                'count' => $assetsPendingApproval,
                'priority' => 'high',
                'icon' => 'check-circle',
                'color' => 'green',
                'route' => route('assets.approvals.index'),
                'category' => 'Assets',
                'current_stage' => 'Reviewed',
                'next_stage' => 'Approve'
            ];
        }

        // 3. TRANSFER WORKFLOW TASKS - Next Stage Actions
        
        // Transfers waiting for approval (pending -> approved)
        $transfersPendingApproval = Transfer::where('status', 'pending')->count();
        if ($transfersPendingApproval > 0) {
            $tasks[] = [
                'id' => 'transfers_pending_approval',
                'type' => 'workflow',
                'title' => 'Transfers Awaiting Approval',
                'description' => "{$transfersPendingApproval} transfers ready for approval",
                'count' => $transfersPendingApproval,
                'priority' => 'medium',
                'icon' => 'truck',
                'color' => 'blue',
                'route' => route('transfers.index', ['status' => 'pending']),
                'category' => 'Transfers',
                'current_stage' => 'Pending',
                'next_stage' => 'Approve'
            ];
        }

        // Transfers ready for dispatch (approved -> dispatched)
        $transfersReadyForDispatch = Transfer::where('status', 'approved')->count();
        if ($transfersReadyForDispatch > 0) {
            $tasks[] = [
                'id' => 'transfers_ready_dispatch',
                'type' => 'workflow',
                'title' => 'Transfers Ready for Dispatch',
                'description' => "{$transfersReadyForDispatch} approved transfers ready for dispatch",
                'count' => $transfersReadyForDispatch,
                'priority' => 'medium',
                'icon' => 'truck',
                'color' => 'green',
                'route' => route('transfers.index', ['status' => 'approved']),
                'category' => 'Transfers',
                'current_stage' => 'Approved',
                'next_stage' => 'Dispatch'
            ];
        }

        // 4. INVENTORY WORKFLOW TASKS - Next Stage Actions
        
        // Low stock items that need reordering
        $lowStockItems = \App\Models\InventoryItem::where('quantity', '>', 0)
            ->whereHas('inventory.product.reorderLevel', function($query) {
                $query->whereRaw('inventory_items.quantity <= (reorder_levels.reorder_level * 0.7)');
            })
            ->count();
        
        if ($lowStockItems > 0) {
            $tasks[] = [
                'id' => 'low_stock_reorder',
                'type' => 'workflow',
                'title' => 'Low Stock - Reorder Needed',
                'description' => "{$lowStockItems} items need immediate reordering",
                'count' => $lowStockItems,
                'priority' => 'high',
                'icon' => 'exclamation-triangle',
                'color' => 'orange',
                'route' => route('inventories.index', ['status' => 'low_stock']),
                'category' => 'Inventory',
                'current_stage' => 'Low Stock',
                'next_stage' => 'Reorder'
            ];
        }

        // Out of stock items that need urgent attention
        $outOfStockItems = \App\Models\InventoryItem::where('quantity', 0)->count();
        if ($outOfStockItems > 0) {
            $tasks[] = [
                'id' => 'out_of_stock_urgent',
                'type' => 'workflow',
                'title' => 'Out of Stock - Urgent Action',
                'description' => "{$outOfStockItems} items completely out of stock",
                'count' => $outOfStockItems,
                'priority' => 'high',
                'icon' => 'x-circle',
                'color' => 'red',
                'route' => route('inventories.index', ['status' => 'out_of_stock']),
                'category' => 'Inventory',
                'current_stage' => 'Out of Stock',
                'next_stage' => 'Restock'
            ];
        }

        // 5. PURCHASE ORDER WORKFLOW TASKS
        
        // Purchase orders ready for packing
        $posReadyForPacking = PurchaseOrder::where('status', 'approved')
            ->whereDoesntHave('packingLists')
            ->count();
        
        if ($posReadyForPacking > 0) {
            $tasks[] = [
                'id' => 'pos_ready_packing',
                'type' => 'workflow',
                'title' => 'POs Ready for Packing',
                'description' => "{$posReadyForPacking} approved POs need packing lists",
                'count' => $posReadyForPacking,
                'priority' => 'medium',
                'icon' => 'archive-box',
                'color' => 'blue',
                'route' => route('purchase-orders.index', ['status' => 'approved']),
                'category' => 'Purchase Orders',
                'current_stage' => 'Approved',
                'next_stage' => 'Create Packing List'
            ];
        }

        // 6. LMIS REPORTS WORKFLOW TASKS
        
        // LMIS reports awaiting review
        $lmisReportsPendingReview = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('facility_monthly_reports')) {
            $lmisReportsPendingReview = \App\Models\FacilityMonthlyReport::where('status', 'submitted')->count();
        }
        if ($lmisReportsPendingReview > 0) {
            $tasks[] = [
                'id' => 'lmis_reports_review',
                'type' => 'report',
                'title' => 'LMIS Reports Awaiting Review',
                'description' => "{$lmisReportsPendingReview} LMIS reports submitted and waiting for review",
                'count' => $lmisReportsPendingReview,
                'priority' => 'medium',
                'icon' => 'clipboard-check',
                'color' => 'yellow',
                'route' => route('reports.facility-lmis-report'),
                'category' => 'Reports',
                'current_stage' => 'Submitted',
                'next_stage' => 'Review'
            ];
        }

        // LMIS reports awaiting approval
        $lmisReportsPendingApproval = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('facility_monthly_reports')) {
            $lmisReportsPendingApproval = \App\Models\FacilityMonthlyReport::where('status', 'reviewed')->count();
        }
        if ($lmisReportsPendingApproval > 0) {
            $tasks[] = [
                'id' => 'lmis_reports_approval',
                'type' => 'report',
                'title' => 'LMIS Reports Awaiting Approval',
                'description' => "{$lmisReportsPendingApproval} LMIS reports reviewed and waiting for approval",
                'count' => $lmisReportsPendingApproval,
                'priority' => 'medium',
                'icon' => 'check-circle',
                'color' => 'green',
                'route' => route('reports.facility-lmis-report'),
                'category' => 'Reports',
                'current_stage' => 'Reviewed',
                'next_stage' => 'Approve'
            ];
        }

        // LMIS reports pending submission
        $lmisReportsPendingSubmission = \App\Models\Facility::whereDoesntHave('monthlyReports', function($query) {
            $query->where('report_period', now()->format('Y-m'));
        })->count();
        
        if ($lmisReportsPendingSubmission > 0) {
            $tasks[] = [
                'id' => 'lmis_reports_submission',
                'type' => 'report',
                'title' => 'LMIS Reports Pending Submission',
                'description' => "{$lmisReportsPendingSubmission} facilities haven't submitted this month's LMIS report",
                'count' => $lmisReportsPendingSubmission,
                'priority' => 'high',
                'icon' => 'document-text',
                'color' => 'red',
                'route' => route('reports.facility-lmis-report'),
                'category' => 'Reports',
                'current_stage' => 'Not Submitted',
                'next_stage' => 'Submit Report'
            ];
        }

        // Sort tasks by priority (high, medium, low) and then by count
        usort($tasks, function($a, $b) {
            $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            if ($priorityOrder[$a['priority']] !== $priorityOrder[$b['priority']]) {
                return $priorityOrder[$b['priority']] - $priorityOrder[$a['priority']];
            }
            return $b['count'] - $a['count'];
        });

        return $tasks;
    }


}
