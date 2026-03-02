<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\ProductController;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DosageController;
use App\Http\Controllers\EligibleItemController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ReverbTestController;
use App\Http\Controllers\ExpiredController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetTypeController;
use App\Http\Controllers\AssetDocumentController;
use App\Http\Controllers\AssetMaintenanceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MohInventoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\LiquidateDisposalController;
use App\Http\Controllers\ConsumptionUploadController;
use App\Http\Controllers\ReceivedBackorderController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;

use App\Http\Controllers\LogisticCompanyController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\ReasonController;
use App\Http\Controllers\ReorderLevelController;
use App\Http\Controllers\AssetDepreciationSettingsController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\EmailNotificationSettingController;
use App\Http\Controllers\ReportScheduleController;
use Maatwebsite\Excel\Facades\Excel;

// Welcome route - accessible without authentication

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Welcome');
})
    ->name('welcome')
    ->middleware('guest');

// Test route for expired stats (no auth required)
Route::get('/test-expired-stats', function() {
    $now = \Carbon\Carbon::now();
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

    return response()->json([
        'expired' => $expiredCount,
        'expiring_within_6_months' => $expiring6MonthsCount,
        'expiring_within_1_year' => $expiring1YearCount,
        'now' => $now->toDateString(),
        'six_months_from_now' => $sixMonthsFromNow->toDateString(),
        'one_year_from_now' => $oneYearFromNow->toDateString(),
    ]);
});

// Broadcast routes
Broadcast::routes(['middleware' => ['web', 'auth']]);

// Two-Factor Authentication Routes - These must be accessible without 2FA
Route::middleware('auth')->group(function () {
    Route::get('/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
    Route::post('/two-factor', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
    Route::post('/two-factor/resend', [TwoFactorController::class, 'resend'])->name('two-factor.resend');
});

// All routes that require authentication and 2FA
Route::middleware(['auth', \App\Http\Middleware\TwoFactorAuth::class])->group(function () {
    
    // Default route - redirect to login or dashboard
    Route::controller(DashboardController::class)
    ->group(function () {
        Route::get('/dashboard', 'index')->name('dashboard');
        Route::post('/warehouse/tracert-items', 'warehouseTracertItems')->name('dashboard.warehouse.tracert-items');
        Route::post('/facility/tracert-items', 'facilityTracertItems')->name('dashboard.facility.tracert-items');
    });

    // Unauthorized access page
    Route::get('/unauthorized', function () {
        return Inertia::render('Unauthorized');
    })->name('unauthorized');


    
    Route::get('/test-import', function() {
        $import = new IssueQuantityItemsImport('2025-06', 1); // Use a real user ID
        logger()->info('TEST ROUTE CALLED');
        Excel::import($import, storage_path('app/test.xlsx'));
        return 'done';
    });

    // Test route for permission events
    Route::get('/test-permission-event', [\App\Http\Controllers\TestController::class, 'testPermissionEvent'])
        ->name('test.permission-event');



    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    // Permissions API endpoint
    Route::get('/api/permissions', [\App\Http\Controllers\PermissionController::class, 'index'])->name('api.permissions.index');
    
    // Role Management Routes
    Route::middleware(PermissionMiddleware::class . ':role.view')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports');

        // LMIS Reports
        Route::get('/reports/lmis-monthly-report', [ReportController::class, 'lmisMonthlyReport'])->name('reports.lmis-monthly');
        Route::put('/reports/lmis-monthly-report/review', [ReportController::class, 'reviewLmisReport'])->name('reports.lmis-monthly.review');
        Route::put('/reports/lmis-monthly-report/approve', [ReportController::class, 'approveLmisReport'])->name('reports.lmis-monthly.approve');
        Route::put('/reports/lmis-monthly-report/reject', [ReportController::class, 'rejectLmisReport'])->name('reports.lmis-monthly.reject');

        // Facility LMIS Reports
        Route::get('/reports/facility-lmis-report', [ReportController::class, 'facilityLmisReport'])->name('reports.facility-lmis-report');
        Route::post('/reports/facility-lmis-report/store', [ReportController::class, 'storeFacilityLmisReport'])->name('reports.facility-lmis-report.store');
        Route::post('/reports/facility-lmis-report/submit', [ReportController::class, 'submitFacilityLmisReport'])->name('reports.facility-lmis-report.submit');
        Route::post('/reports/facility-lmis-report/generate-from-movements', [ReportController::class, 'generateFacilityLmisReportFromMovements'])->name('reports.facility-lmis-report.generate-from-movements');
        Route::get('/reports/facility-lmis-report/create', [ReportController::class, 'createFacilityLmisReport'])->name('reports.facility-lmis-report.create');
    });

    // Category Management Routes (product-view or product-manage)
    Route::middleware([\App\Http\Middleware\TwoFactorAuth::class, PermissionMiddleware::class . ':product-view,product-manage'])
        ->group(function () {
            Route::get('/categories', [CategoryController::class, 'index'])->name('products.categories.index');
            Route::post('/categories/store', [CategoryController::class, 'store'])->name('products.categories.store');
            Route::get('/categories/create', [CategoryController::class, 'create'])->name('products.categories.create');
            Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])
                ->name('products.categories.edit');
            Route::get('/categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])
                // ->middleware(PermissionMiddleware::class . ':category.edit')
                ->name('products.categories.toggle-status');

            Route::get('/categories/{category}/destroy', [CategoryController::class, 'destroy'])
                ->name('categories.destroy');
        });

    // Warehouse Management Routes
    Route::controller(WarehouseController::class)
        ->prefix('/inventories/warehouses')
        ->group(function () {
            Route::get('/', 'index')->name('inventories.warehouses.index');
            Route::post('/store', 'store')->name('inventories.warehouses.store');
            Route::get('/create', 'create')->name('inventories.warehouses.create');
            Route::delete('/{id}/delete', 'destroy')->name('inventories.warehouses.destroy');
            Route::get('/{id}/edit', 'edit')->name('inventories.warehouses.edit');

            // delete warehouse
            Route::get('/{id}/toggle-status', 'toggleStatus')->name('inventories.warehouses.toggle-status');
            Route::post('/get-warehouses', 'getWarehousesPluck')->name('warehouses.get-warehouses');

        });
    });


     // Warehouse Management Routes
Route::controller(LocationController::class)
     ->prefix('/inventories/locations')
     ->group(function () {
         Route::get('/', 'index')->name('inventories.location.index');
         Route::post('/store', 'store')->name('inventories.location.store');
         Route::delete('/{id}/delete', 'destroy')->name('inventories.location.destroy');
         Route::get('/{id}/edit', 'edit')->name('inventories.location.edit');
         Route::get('/create', 'create')->name('inventories.location.create');

        // 'warehouse.locations
        Route::get('/{id}/locations', 'getLocations')->name('warehouse.locations');
    });

    // Dosage Management Routes
    Route::prefix('product/dosages')->group(function () {
            Route::get('/', [DosageController::class, 'index'])->name('products.dosages.index');
            Route::get('/create', [DosageController::class, 'create'])->name('products.dosages.create');
            Route::post('/store', [DosageController::class, 'store'])->name('products.dosages.store');
            Route::get('/{dosage}/edit', [DosageController::class, 'edit'])->name('products.dosages.edit');
        Route::get('/{dosage}/toggle-status', [DosageController::class, 'toggleStatus'])->name('products.dosages.toggle-status');
            Route::delete('/{dosage}', [DosageController::class, 'destroy'])->name('products.dosages.destroy');
    });

    // Product Management Routes
    Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('products.index');
            Route::get('/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('/', [ProductController::class, 'store'])->name('products.store');
            Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
            Route::put('/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::delete('/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/import-excel', [ProductController::class, 'importExcel'])->name('products.import-excel');
            Route::get('/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle-status');
        Route::get('/import-status/{importId}', [ProductController::class, 'checkImportStatus'])
            ->name('products.import-status');
    });

    // Eligible Items Management Routes
    Route::prefix('eligible-items')->group(function () {
            Route::get('/', [EligibleItemController::class, 'index'])->name('products.eligible.index');
            Route::get('/create', [EligibleItemController::class, 'create'])->name('products.eligible.create');
            Route::post('/store', [EligibleItemController::class, 'store'])->name('products.eligible.store');
            Route::get('/{eligibleItem}/edit', [EligibleItemController::class, 'edit'])->name('products.eligible.edit');
            Route::post('/update', [EligibleItemController::class, 'update'])->name('products.eligible.update');
            Route::get('/{id}/delete', [EligibleItemController::class, 'destroy'])->name('products.eligible.destroy');
            Route::post('/import', [EligibleItemController::class, 'import'])->name('products.eligible.import');
    });

    // UOM Management Routes
    Route::prefix('uom')->group(function () {
            Route::get('/list', [\App\Http\Controllers\UomController::class, 'list'])->name('products.uom.list');
            Route::get('/', [\App\Http\Controllers\UomController::class, 'index'])->name('products.uom.index');
            Route::get('/create', [\App\Http\Controllers\UomController::class, 'create'])->name('products.uom.create');
            Route::post('/store', [\App\Http\Controllers\UomController::class, 'store'])->name('products.uom.store');
            Route::get('/{uom}/edit', [\App\Http\Controllers\UomController::class, 'edit'])->name('products.uom.edit');
            Route::put('/{uom}', [\App\Http\Controllers\UomController::class, 'update'])->name('products.uom.update');
            Route::delete('/{uom}', [\App\Http\Controllers\UomController::class, 'destroy'])->name('products.uom.destroy');
        Route::get('/{uom}/toggle-status', [\App\Http\Controllers\UomController::class, 'toggleStatus'])->name('products.uom.toggle-status');
    });

    // Facility Type Management Routes
    Route::prefix('facility-types')->group(function () {
            Route::get('/', [\App\Http\Controllers\FacilityTypeController::class, 'index'])->name('products.facility-types.index');
            Route::get('/create', [\App\Http\Controllers\FacilityTypeController::class, 'create'])->name('products.facility-types.create');
            Route::post('/store', [\App\Http\Controllers\FacilityTypeController::class, 'store'])->name('products.facility-types.store');
            Route::get('/{facilityType}/edit', [\App\Http\Controllers\FacilityTypeController::class, 'edit'])->name('products.facility-types.edit');
            Route::delete('/{facilityType}', [\App\Http\Controllers\FacilityTypeController::class, 'destroy'])->name('products.facility-types.destroy');
        Route::get('/{facilityType}/toggle-status', [\App\Http\Controllers\FacilityTypeController::class, 'toggleStatus'])->name('products.facility-types.toggle-status');
    });

    // Supply Management Routes
    Route::prefix('supplies')->group(function () {
        // View routes - require purchase-order-view permission
        Route::get('/', [SupplyController::class, 'index'])->name('supplies.index');
        Route::get('/show', [SupplyController::class, 'show'])->name('supplies.show');
        Route::get('/{id}/showPO', [SupplyController::class, 'showPO'])->name('supplies.po-show');
        Route::get('/packing-list/show', [SupplyController::class, 'showPK'])->name('supplies.packing-list.showPK');
        Route::get('/packing-list/{id}/show', [SupplyController::class, 'showPackingList'])->name('supplies.packing-list.show');
        Route::get('/back-orders', [SupplyController::class, 'showBackOrder'])->name('supplies.showBackOrder');

        // Create routes - require create permissions
        Route::get('/create', [SupplyController::class, 'create'])->name('supplies.create');
        Route::get('/purchase_orders', [SupplyController::class, 'newPO'])->name('supplies.purchase_order');
        Route::get('/packing-list/create', [SupplyController::class, 'newPackingList'])->name('supplies.packing-list.create');
        Route::get('/packing-list', [SupplyController::class, 'newPackingList'])->name('supplies.packing-list');

        // Edit routes - require edit permissions
        Route::get('/packing-list/{id}/edit', [SupplyController::class, 'editPK'])->name('supplies.packing-list.edit');
        Route::get('/purchase_orders/{id}/edit', [SupplyController::class, 'editPO'])->name('supplies.editPO');
        Route::get('/{supply}/edit', [SupplyController::class, 'edit'])->name('supplies.edit');

        // Store/Update routes - require create/edit permissions
        Route::post('/store', [SupplyController::class, 'store'])->name('supplies.store');
        Route::put('/{supply}', [SupplyController::class, 'update'])->name('supplies.update');
        Route::post('/purchase_orders/store', [SupplyController::class, 'storePO'])->name('supplies.storePO');
        Route::put('/purchase_orders/{id}/update', [SupplyController::class, 'updatePurchaseOrder'])->name('supplies.updatePO');
        Route::post('/packing-list/store', [SupplyController::class, 'storePK'])->name('supplies.storePK');
        Route::post('/packing-list/update', [SupplyController::class, 'updatePK'])->name('supplies.packing-list.update');

        // Delete routes - require delete permissions
        Route::get('/{id}/delete', [SupplyController::class, 'destroy'])->name('supplies.deletePO');
        Route::delete('/{supply}', [SupplyController::class, 'destroy'])->name('supplies.destroy');
        Route::delete('/purchase_orders/documents/{document}', [SupplyController::class, 'deleteDocument'])->name('supplies.deleteDocument');

        // Document upload routes
        Route::post('/{id}/upload-document', [SupplyController::class, 'uploadDocument'])->name('supplies.uploadDocument');
        Route::post('/packing-list/{id}/upload-document', [SupplyController::class, 'uploadPackingListDocument'])->name('supplies.packing-list.uploadDocument');

        // Utility routes
        Route::get('/packing-list/{id}/get-po', [SupplyController::class, 'getPO'])->name('supplies.get-purchaseOrder');
        Route::get('/packing-list/{id}/get-back-order', [SupplyController::class, 'getBackOrder'])->name('supplies.get-back-order');

        // supplies.back-order
        Route::get('/back-order', [SupplyController::class, 'backOrder'])->name('supplies.back-order');

        // supplies.get-packingList
        Route::get('/packing-list/{id}/get-back-order', [SupplyController::class, 'getBackOrder'])->name('supplies.get-back-order');

        // Purchase Order Actions - require specific permissions
        Route::post('/purchase_orders/{id}/review', [SupplyController::class, 'reviewPO'])->name('supplies.reviewPO');
        Route::post('/purchase_orders/{id}/approve', [SupplyController::class, 'approvePO'])->name('supplies.approvePO');
        Route::post('/purchase_orders/{id}/reject', [SupplyController::class, 'rejectPO'])->name('supplies.rejectPO');

        // Packing List Actions - require specific permissions
        Route::post('/packing-list/review', [SupplyController::class, 'reviewPK'])->name('supplies.reviewPK');
        Route::post('/packing-list/approve', [SupplyController::class, 'approvePK'])->name('supplies.approvePK');
        Route::post('/packing-list/reject', [SupplyController::class, 'rejectPK'])->name('supplies.rejectPK');

            Route::post('/back-order/dispose', [SupplyController::class, 'dispose'])->name('back-order.dispose');
            Route::post('/back-order/receive', [SupplyController::class, 'receive'])->name('back-order.receive');
            Route::post('/store-location', [SupplyController::class, 'storeLocation'])->name('supplies.store-location');
               
    
        Route::post('/store', [SupplyController::class, 'store'])->name('supplies.store');
        Route::get('/{supply}/edit', [SupplyController::class, 'edit'])->name('supplies.edit');
        Route::put('/{supply}', [SupplyController::class, 'update'])->name('supplies.update');
            Route::delete('/{supply}', [SupplyController::class, 'destroy'])->name('supplies.destroy');

        // supplies.packing-list.update
        Route::post('/packing-list/update', [SupplyController::class, 'updatePK'])->name('supplies.packing-list.update');


        // back-order.liquidate
        Route::post('/liquidate', [SupplyController::class, 'liquidate'])->name('back-order.liquidate');

        // back-order.dispose
        Route::post('/dispose', [SupplyController::class, 'dispose'])->name('back-order.dispose');
        Route::get('/packing-list/{id}/show', [SupplyController::class, 'showPackingList'])->name('supplies.packing-list.show');

        // Add new route for packing list document upload
        Route::post('/packing-list/{id}/upload-document', [SupplyController::class, 'uploadPackingListDocument'])
            ->name('supplies.packing-list.uploadDocument');

        Route::get('/back-orders/list', [\App\Http\Controllers\SupplyController::class, 'listBackOrders'])->name('supplies.backOrders.list');
        Route::get('/back-orders/{id}/histories', [\App\Http\Controllers\SupplyController::class, 'getBackOrderHistories'])->name('supplies.backOrders.histories');
        Route::post('/back-orders/{id}/attachments', [\App\Http\Controllers\SupplyController::class, 'uploadBackOrderAttachment'])->name('supplies.backOrders.uploadAttachment');
        Route::delete('/back-orders/{id}/attachments', [\App\Http\Controllers\SupplyController::class, 'deleteBackOrderAttachment'])->name('supplies.backOrders.deleteAttachment');

        // Received Back Order Routes
        Route::get('/received-backorder', [ReceivedBackorderController::class, 'index'])->name('supplies.received-backorder.index');
        Route::get('/received-backorder/create', [ReceivedBackorderController::class, 'create'])->name('supplies.received-backorder.create');
        Route::post('/received-backorder', [ReceivedBackorderController::class, 'store'])->name('supplies.received-backorder.store');
        Route::get('/received-backorder/{receivedBackorder}', [ReceivedBackorderController::class, 'show'])->name('supplies.received-backorder.show');


        Route::delete('/received-backorder/{receivedBackorder}', [ReceivedBackorderController::class, 'destroy'])->name('supplies.received-backorder.destroy');
        
        // Received Back Order Action Routes
        Route::post('/received-backorder/{receivedBackorder}/review', [ReceivedBackorderController::class, 'review'])->name('supplies.received-backorder.review');
        Route::post('/received-backorder/{receivedBackorder}/approve', [ReceivedBackorderController::class, 'approve'])->name('supplies.received-backorder.approve');
        Route::post('/received-backorder/{receivedBackorder}/reject', [ReceivedBackorderController::class, 'reject'])->name('supplies.received-backorder.reject');
        Route::delete('/received-backorder/{receivedBackorder}/attachments', [ReceivedBackorderController::class, 'deleteAttachment'])->name('supplies.received-backorder.deleteAttachment');

    });

    // Liquidate & Disposal Management Routes
    Route::controller(LiquidateDisposalController::class)
        ->name('liquidate-disposal.')
        ->prefix('liquidate-disposal')
        ->group(function(){
            Route::get('/', 'index')->name('index');
            Route::get('/liquidates/{id}', 'showLiquidate')->name('liquidates.show');
            Route::get('/disposals/{id}', 'showDisposal')->name('disposals.show');
            
            // Liquidation action routes
            Route::post('/liquidates/{id}/review', 'reviewLiquidate')->name('liquidates.review');
            Route::post('/liquidates/{id}/approve', 'approveLiquidate')->name('liquidates.approve');
            Route::post('/liquidates/{id}/reject', 'rejectLiquidate')->name('liquidates.reject');
            Route::post('/liquidates/{id}/rollback', 'rollbackLiquidate')->name('liquidates.rollback');
            
            // Disposal action routes
            Route::post('/disposals/{id}/review', 'reviewDisposal')->name('disposals.review');
            Route::post('/disposals/{id}/approve', 'approveDisposal')->name('disposals.approve');
            Route::post('/disposals/{id}/reject', 'rejectDisposal')->name('disposals.reject');
            Route::post('/disposals/{id}/rollback', 'rollbackDisposal')->name('disposals.rollback');
        });

    // Supplier Management Routes
    Route::prefix('suppliers')->group(function () {
            Route::get('/', [SupplierController::class, 'index'])->name('suppliers.index');
            // Route::get('/create', [SupplierController::class, 'create'])->name('supplies.suppliers.create');
            Route::get('/{id}/edit', [SupplierController::class, 'edit'])->name('supplies.suppliers.edit');
            Route::post('/', [SupplierController::class, 'store'])->name('supplies.suppliers.store');
            Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
            Route::put('/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    // Expired Management Routes
    Route::prefix('expired')->group(function () {
        Route::get('/', [ExpiredController::class, 'index'])->name('expired.index');
        Route::get('/create', [ExpiredController::class, 'create'])->name('expired.create');
        Route::post('/', [ExpiredController::class, 'store'])->name('expired.store');
        Route::get('/{expired}/edit', [ExpiredController::class, 'edit'])->name('expired.edit');
        Route::put('/{expired}', [ExpiredController::class, 'update'])->name('expired.update');
        Route::delete('/{expired}', [ExpiredController::class, 'destroy'])->name('expired.destroy');
        Route::get('/{transfer}/transfer', [ExpiredController::class, 'transfer'])->name('expired.transfer');
        Route::post('/dispose', [ExpiredController::class, 'dispose'])->name('expired.dispose');
    });

    // Reorder Level Management Routes
    Route::prefix('reorder-levels')->group(function () {
        Route::get('/', [ReorderLevelController::class, 'index'])->name('reorder-levels.index');
        Route::get('/create', [ReorderLevelController::class, 'create'])->name('reorder-levels.create');
        Route::post('/', [ReorderLevelController::class, 'store'])->name('reorder-levels.store');
        Route::get('/{reorderLevel}/edit', [ReorderLevelController::class, 'edit'])->name('reorder-levels.edit');
        Route::put('/{reorderLevel}', [ReorderLevelController::class, 'update'])->name('reorder-levels.update');
        Route::delete('/{reorderLevel}', [ReorderLevelController::class, 'destroy'])->name('reorder-levels.destroy');
        Route::post('/import', [ReorderLevelController::class, 'importExcel'])->name('reorder-levels.import');
        Route::get('/import/format', [ReorderLevelController::class, 'getImportFormat'])->name('reorder-levels.import.format');
    });

    // Order Management Routes (same as transfer: view, create, edit, delete, manage + workflow; workflow actions checked in controller)
    Route::middleware(PermissionMiddleware::class . ':order-view,order-create,order-edit,order-delete,order-manage,order-review,order-approve,order-reject,order-processing,order-dispatch')->group(function () {
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/{id}/show', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/change-status', [OrderController::class, 'changeStatus'])->name('orders.change-status');
        Route::post('/reject', [OrderController::class, 'rejectOrder']);

        // restore order
        Route::post('/restore-order', [OrderController::class, 'restoreOrder'])->name('orders.restore-order');

        Route::get('/create', [OrderController::class, 'create'])->name('orders.create');
        Route::post('/', [OrderController::class, 'store'])->name('orders.store');
        Route::get('/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
        Route::put('/{order}', [OrderController::class, 'update'])->name('orders.update');
        Route::delete('/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');

        // dispatch info
        Route::post('/dispatch-info', [OrderController::class, 'dispatchInfo'])->name('orders.dispatch-info');
        
        // 'order.update-quantity
        Route::post('/update-quantity', [OrderController::class, 'updateQuantity'])->name('orders.update-quantity');

    });
    });

    // Transfer Management Routes
    // ->middleware(PermissionMiddleware::class . ':transfer.view')
    Route::prefix('transfers')->group(function () {
        Route::get('/', [TransferController::class, 'index'])->name('transfers.index');
        Route::get('/{id}/show', [TransferController::class, 'show'])->name('transfers.show');
        Route::get('/create', [TransferController::class, 'create'])->name('transfers.create');
        Route::post('/store', [TransferController::class, 'store'])->name('transfers.store');
        Route::get('/{transfer}/edit', [TransferController::class, 'edit'])->name('transfers.edit');
        Route::put('/{transfer}', [TransferController::class, 'update'])->name('transfers.update');
        Route::delete('/{transfer}', [TransferController::class, 'destroy'])->name('transfers.destroy');

        // get inventory for transfer
        Route::post('/inventory', [TransferController::class, 'getSourceInventoryDetail'])->name('transfers.inventory');
                
        // Route to get available inventories for transfer
        Route::get('/get-inventories', [TransferController::class, 'getInventories'])->name('transfers.getInventories');
               
         
        // Back order functionality
        Route::post('/backorder', [TransferController::class, 'backorder'])->name('transfers.backorder');
        Route::post('/remove-back-order', [TransferController::class, 'removeBackOrder'])->name('transfers.remove-back-order');
        
        // Item status change
        Route::post('/change-item-status', [TransferController::class, 'changeItemStatus'])->name('transfers.changeItemStatus');
        
        // receive transfer
        Route::post('/receive', [TransferController::class, 'receiveTransfer'])->name('transfers.receiveTransfer');
        
        // receive back order
        Route::post('/receive-back-order', [TransferController::class, 'receiveBackOrder'])->name('transfers.receiveBackOrder');
        
        // delete transfer item
        // destroyItem route removed

        // update transfer item quantity
        Route::post('/update-item', [TransferController::class, 'updateItem'])->name('transfers.update-item');

        // transfer back order
        Route::get('/back-order', [TransferController::class, 'transferBackOrder'])->name('transfers.back-order');

        // transfer liquidate
        Route::post('/liquidate', [TransferController::class, 'transferLiquidate'])->name('transfers.liquidate');

        // transfer dispose
        Route::post('/dispose', [TransferController::class, 'transferDispose'])->name('transfers.dispose');


         // transfer update-quantity
         Route::post('/update-quantity', [TransferController::class, 'updateQuantity'])->name('transfers.update-quantity');

         // save transfer back orders
         Route::post('/save-back-orders', [TransferController::class, 'saveBackOrders'])->name('transfers.save-back-orders');
         
         // delete transfer back order
         // deleteBackOrder route removed
          // change transfer status
          Route::post('/change-status', [TransferController::class, 'changeStatus'])->name('transfers.change-status');
          
          // restore transfer
          Route::post('/restore-transfer', [TransferController::class, 'restoreTransfer'])->name('transfers.restore-transfer');

          Route::post('/dispatch-info', [TransferController::class, 'dispatchInfo'])->name('transfers.dispatch-info');

            // mark transfer as delivered
            Route::post('/mark-delivered', [TransferController::class, 'markDelivered'])->name('transfers.mark-delivered');

            // Add routes for drivers and logistics companies
            Route::get('/get-drivers', [TransferController::class, 'getDrivers'])->name('transfers.get-drivers');
            Route::get('/get-logistic-companies', [TransferController::class, 'getLogisticCompanies'])->name('transfers.get-logistic-companies');
            Route::post('/add-driver', [TransferController::class, 'addDriver'])->name('transfers.add-driver');

            // receivedQuantity
            Route::post('/update-received-quantity', [TransferController::class, 'receivedQuantity'])->name('transfers.receivedQuantity');
    });

    // Purchase Order Management Routes
    Route::prefix('purchase-orders')->group(function () {
            Route::get('/', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
            Route::get('/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
            Route::post('/', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
            Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
            Route::get('/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
            Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
            Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy');
        
        // Import routes
            Route::post('/import', [PurchaseOrderController::class, 'importItems'])->name('purchase-orders.import');
        Route::get('/import/progress', [PurchaseOrderController::class, 'getImportProgress'])->name('purchase-orders.import.progress');
    });

    // Dispatch Management Routes
    Route::prefix('dispatches')->group(function () {
            Route::get('/', [DispatchController::class, 'index'])->name('dispatches.index');
            Route::get('/create', [DispatchController::class, 'create'])->name('dispatches.create');
            Route::post('/', [DispatchController::class, 'store'])->name('dispatches.store');
            Route::get('/{dispatch}/edit', [DispatchController::class, 'edit'])->name('dispatches.edit');
            Route::put('/{dispatch}', [DispatchController::class, 'update'])->name('dispatches.update');
            Route::delete('/{dispatch}', [DispatchController::class, 'destroy'])->name('dispatches.destroy');
    });

    // Facility Management Routes (facility-view or facility-manage)
    Route::middleware(PermissionMiddleware::class . ':facility-view,facility-manage')->group(function () {
    Route::prefix('facilities')->group(function () {
        Route::get('/', [FacilityController::class, 'index'])->name('facilities.index');
        Route::get('/{id}/show', [FacilityController::class, 'show'])->name('facilities.show');
        Route::get('/create', [FacilityController::class, 'create'])->name('facilities.create');
        Route::post('/import', [FacilityController::class, 'import'])->name('facilities.import');
        Route::get('/download-template', [FacilityController::class, 'downloadTemplate'])->name('facilities.download-template');
        Route::post('/store', [FacilityController::class, 'store'])->name('facilities.store');
        Route::get('/{facility}/edit', [FacilityController::class, 'edit'])->name('facilities.edit');
        Route::delete('/{facility}', [FacilityController::class, 'destroy'])->name('facilities.destroy');
        Route::get('/{facility}/toggle-status', [FacilityController::class, 'toggleStatus'])->name('facilities.toggle-status');

        // tabs
        Route::get('/{facility}/inventory', [FacilityController::class, 'inventory'])->name('facilities.inventory');
        Route::get('/{facility}/dispence', [FacilityController::class, 'dispence'])->name('facilities.dispence');
        Route::get('/{facility}/expiry', [FacilityController::class, 'expiry'])->name('facilities.expiry');

        // get facilities
        Route::post('/get-facilities', [FacilityController::class, 'getFacilities'])->name('facilities.get-facilities');
    });
    });

    // District Management Routes (under facility module: facility-view or facility-manage)
    Route::middleware(PermissionMiddleware::class . ':facility-view,facility-manage')->group(function () {
    Route::prefix('districts')->group(function () {
            Route::get('/', [DistrictController::class, 'index'])->name('districts.index');
            Route::get('/create', [DistrictController::class, 'create'])->name('districts.create');
            Route::post('/', [DistrictController::class, 'store'])->name('districts.store');
            Route::get('/{district}/edit', [DistrictController::class, 'edit'])->name('districts.edit');
            Route::put('/{district}', [DistrictController::class, 'update'])->name('districts.update');
            Route::delete('/{district}', [DistrictController::class, 'destroy'])->name('districts.destroy');

        // get district by region
        Route::post('/get-districts', [DistrictController::class, 'getDistricts'])->name('districts.get-districts');
        Route::post('/store', [DistrictController::class, 'store'])->name('districts.store');
    });
    });

    // Asset Management Routes (any asset permission grants route access; controller enforces per-action)
    Route::middleware(PermissionMiddleware::class . ':asset-view,asset-create,asset-edit,asset-delete,asset-review,asset-approve,asset-reject,asset-manage,asset-bulk-import,asset-export')->group(function () {
    Route::controller(AssetController::class)
        ->prefix('assets-management')
        ->group(function () {
            // View routes - require asset-view permission
            Route::get('/', 'index')->name('assets.index');
            Route::get('/{asset}/show', 'show')->name('assets.show');
            Route::get('/approvals', 'approvalsIndex')->name('assets.approvals.index');
            Route::get('/workflow', 'approvalsIndex')->name('assets.workflow.index');
            Route::get('/asset-items/{assetItem}/history', 'showHistory')->name('assets.history.index');
            Route::get('/asset-items/{assetItem}/detailed-history', 'showAssetItemHistory')->name('assets.items.history.index');
            
            // Create routes - require asset-create permission
            Route::get('/create', 'create')->name('assets.get-create');
            Route::post('/store', 'store')->name('assets.store');
            
            // Edit routes - require asset-edit permission
            Route::get('/{asset}/edit', 'edit')->name('assets.edit');
            Route::put('/{asset}/update', 'update')->name('assets.update');
            
            // Delete routes - require asset-delete permission
            Route::delete('/{asset}/delete', 'destroy')->name('assets.destroy');
            
            // Approval routes - require asset-approve permission
            Route::post('/{asset}/approve', 'approve')->name('assets.approve');
            Route::post('/{asset}/reject', 'reject')->name('assets.reject');
            Route::post('/{asset}/review', 'review')->name('assets.review');
            Route::post('/{asset}/restore', 'restore')->name('assets.restore');
            Route::post('/bulk-approve', 'bulkApprove')->name('assets.bulk-approve');
            
            // Transfer routes - require asset-manage permission
            Route::post('/{asset}/transfer', 'transferAsset')->name('assets.transfer');
            
            // Bulk operations - require asset-bulk-import permission
            Route::get('/template/download', 'downloadTemplate')->name('assets.template.download');
            Route::post('/import', 'import')->name('assets.import');
            
            // Asset locations routes - require asset-manage permission
            Route::get('/locations', 'locationIndex')->name('assets.locations.index');
            Route::get('/sub-locations', 'subLocationIndex')->name('assets.sub-locations.index');
            Route::get('/locations/{location}/sub-locations', 'getSubLocations')->name('assets.locations.sub-locations');
            Route::post('/locations/sub-locations', 'storeSubLocation')->name('assets.locations.sub-locations.store');
            Route::post('/categories/store', 'storeCategory')->name('assets.categories.store');
            Route::post('/types/store', [AssetTypeController::class, 'store'])->name('assets.types.store');
            Route::post('/locations/store', 'storeAssetLocation')->name('assets.locations.store');
            Route::post('/fund-sources/store', 'storeFundSource')->name('assets.fund-sources.store');
            Route::post('/regions/store', 'storeRegion')->name('assets.regions.store');

            // Asset Assignee Routes - require asset-manage permission
            Route::post('/assignees/store', 'storeAssignee')->name('assets.assignees.store');

            // Asset Document Routes - require asset-edit permission
            Route::post('/{asset}/documents', [AssetDocumentController::class, 'store'])->name('asset.documents.store');
            Route::delete('/documents/{document}', [AssetDocumentController::class, 'destroy'])->name('asset.documents.destroy');
            Route::get('/documents/{document}/download', [AssetDocumentController::class, 'download'])->name('asset.documents.download');
            Route::get('/documents/{document}/preview', [AssetDocumentController::class, 'preview'])->name('asset.documents.preview');

            // Asset Maintenance Routes - require asset-edit permission
            Route::get('/{asset}/maintenance', [AssetMaintenanceController::class, 'index'])->name('asset.maintenance.index');
            Route::post('/{asset}/maintenance', [AssetMaintenanceController::class, 'store'])->name('asset.maintenance.store');
            Route::get('/maintenance/{maintenance}/edit', [AssetMaintenanceController::class, 'edit'])->name('asset.maintenance.edit');
            Route::put('/maintenance/{maintenance}', [AssetMaintenanceController::class, 'update'])->name('asset.maintenance.update');
            Route::delete('/maintenance/{maintenance}', [AssetMaintenanceController::class, 'destroy'])->name('asset.maintenance.destroy');
            Route::post('/maintenance/{maintenance}/mark-completed', [AssetMaintenanceController::class, 'markCompleted'])->name('asset.maintenance.mark-completed');
            Route::get('/{asset}/maintenance/list', [AssetMaintenanceController::class, 'getAssetMaintenance'])->name('asset.maintenance.list');
        });
    });

    // Inventory Management Routes (inventory-view or inventory-manage or inventory-adjust or inventory-transfer)
    Route::middleware(PermissionMiddleware::class . ':inventory-view,inventory-manage,inventory-adjust,inventory-transfer')->group(function () {
    Route::prefix('inventory')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('inventories.index');
        Route::get('/create', [InventoryController::class, 'create'])->name('inventories.create');
        Route::post('/', [InventoryController::class, 'store'])->name('inventories.store');
        Route::get('/{inventory}/edit', [InventoryController::class, 'edit'])->name('inventories.edit');
        Route::put('/{inventory}', [InventoryController::class, 'update'])->name('inventories.update');
        Route::delete('/{inventory}', [InventoryController::class, 'destroy'])->name('inventories.destroy');
        Route::patch('/update-location', [InventoryController::class, 'updateLocation'])->name('inventories.update-location');
        Route::get('/get-locations', [InventoryController::class, 'getLocations'])->name('inventories.getLocations');
        Route::get('/get-all-locations', [InventoryController::class, 'getAllLocations'])->name('inventories.getAllLocations');
        Route::post('/import', [InventoryController::class, 'import'])->name('inventories.import');
    });
    });

    // API Routes
    Route::prefix('api')->group(function () {
        // API routes removed - data now comes from controller props
    });

    // Report Routes
    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('reports.index');

        // Unified Reports page
        Route::get('/inventory-reports', [ReportController::class, 'inventoryReportsUnified'])->name('reports.inventoryReportsUnified');
        Route::get('/inventory-reports/data', [ReportController::class, 'inventoryReportsUnifiedData'])->name('reports.inventoryReportsUnified.data');
        Route::patch('/inventory-reports/items/{id}', [ReportController::class, 'updateInventoryReportItem'])->name('reports.inventoryReportItem.update');
        Route::patch('/inventory-reports/{id}', [ReportController::class, 'updateInventoryReport'])->name('reports.inventoryReport.update');

        // Physical Count
        Route::get('/facilities-list', [ReportController::class, 'facilitiesList'])->name('reports.facilities-list');
        Route::get('/physicalCount', [ReportController::class, 'physicalCountReport'])->name('reports.physicalCount');
        Route::get('/physical-count-show', [ReportController::class, 'physicalCountShow'])->name('reports.physicalCountShow');
        Route::post('/physical-count/generate', [ReportController::class, 'generatePhysicalCountReport'])->name('reports.physicalCountReport');
        Route::post('/physical-count/update', [ReportController::class, 'updatePhysicalCountReport'])->name('reports.physical-count.update');
        Route::post('/physical-count/status', [ReportController::class, 'updatePhysicalCountStatus'])->name('reports.physical-count.status');
        Route::post('/physical-count/approve', [ReportController::class, 'approvePhysicalCountReport'])->name('reports.physical-count.approve');
        Route::post('/physical-count/reject', [ReportController::class, 'rejectPhysicalCountReport'])->name('reports.physical-count.reject');
        Route::post('/physical-count/rollback', [ReportController::class, 'rollBackRejectPhysicalCountReport'])->name('reports.physical-count.rollback');

        // Warehouse Monthly Inventory Report (adjustments workflow)
        Route::get('/warehouse-monthly', [ReportController::class, 'warehouseMonthlyReport'])->name('reports.warehouseMonthly');
        Route::put('/warehouse-monthly/adjustments', [ReportController::class, 'updateInventoryReportAdjustments'])->name('reports.warehouseMonthly.updateInventoryReportAdjustments');
        Route::put('/warehouse-monthly/submit', [ReportController::class, 'submitInventoryReport'])->name('reports.warehouseMonthly.submit');
        Route::put('/warehouse-monthly/review', [ReportController::class, 'reviewInventoryReport'])->name('reports.warehouseMonthly.review');
        Route::put('/warehouse-monthly/approve', [ReportController::class, 'approveInventoryReport'])->name('reports.warehouseMonthly.approve');
        Route::put('/warehouse-monthly/reject', [ReportController::class, 'rejectInventoryReport'])->name('reports.warehouseMonthly.reject');
        Route::post('/warehouse-monthly/export-to-excel', [ReportController::class, 'exportToExcel'])->name('reports.warehouseMonthly.exportToExcel');

        // Warehouse AMC: no report page; data is fed by schedule (warehouse:generate-amc) into warehouse_amcs and reorder_levels
    });

    // MOH Inventory Routes (own permissions: view, create, review, approve, reject)
    Route::middleware(PermissionMiddleware::class . ':moh-inventory-view,moh-inventory-create,moh-inventory-review,moh-inventory-approve,moh-inventory-reject')->group(function () {
    Route::controller(MohInventoryController::class)
    ->group(function () {
        Route::get('/moh-inventory', 'index')->name('inventories.moh-inventory.index');
        Route::post('/moh-inventory', 'store')->name('inventories.moh-inventory.store');
        Route::post('/moh-inventory/import', 'import')->name('inventories.moh-inventory.import');
        Route::get('/moh-inventory/import-progress', 'getImportProgress')->name('inventories.moh-inventory.import-progress');
        Route::get('/moh-inventory/test-import', 'testImport')->name('inventories.moh-inventory.test-import');
        Route::post('/moh-inventory/{mohInventory}/change-status', 'changeStatus')->name('inventories.moh-inventory.change-status');
        Route::put('/moh-inventory/{mohInventoryItem}', 'updateItem')->name('inventories.moh-inventory.update-item');
    });
    });

    // Approval Routes
    // Reason Management Routes
    Route::controller(ReasonController::class)->prefix('reasons')->group(function () {
        Route::get('/', 'index')->name('reasons.index');
        Route::post('/store', 'store')->name('reasons.store');
        Route::delete('/destroy', 'destroy')->name('reasons.destroy');
        Route::get('/get-reasons', 'getReasons')->name('reasons.get-reasons');
    });
    
    // Approval Routes
    Route::middleware(['auth', \App\Http\Middleware\TwoFactorAuth::class])->prefix('approvals')->group(function () {
        Route::get('/', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::get('/{approval}', [ApprovalController::class, 'show'])->name('approvals.show');
        Route::post('/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    });

    // Settings Management Routes
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('settings.index');
        
        // Users routes
        Route::get('/users', [UserController::class, 'index'])->name('settings.users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('settings.users.create');
        Route::post('/users', [UserController::class, 'store'])->name('settings.users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('settings.users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('settings.users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('settings.users.destroy');
        Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('settings.users.toggle-status');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('settings.users.reset-password');
        Route::post('/users/{user}/assign-permissions', [UserController::class, 'assignPermissions'])->name('settings.users.assign-permissions');
        Route::get('/users/{user}/permissions', [UserController::class, 'getUserPermissions'])->name('settings.users.permissions');
        
        // Roles
        Route::get('/roles', [RoleController::class, 'index'])->name('settings.roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('settings.roles.store');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('settings.roles.destroy');
        
        // Email notifications (programmable)
        Route::get('/email-notifications', [EmailNotificationSettingController::class, 'index'])->name('settings.email-notifications.index');
        Route::put('/email-notifications', [EmailNotificationSettingController::class, 'update'])->name('settings.email-notifications.update');

        // Report schedules (programmable; use Laravel schedule:run in cron)
        Route::get('/report-schedules', [ReportScheduleController::class, 'index'])->name('settings.report-schedules.index');
        Route::put('/report-schedules', [ReportScheduleController::class, 'update'])->name('settings.report-schedules.update');
        Route::post('/report-schedules/run-inventory-monthly-report', [ReportScheduleController::class, 'runInventoryMonthlyReportNow'])->name('settings.report-schedules.run-inventory-monthly-report');
        Route::post('/report-schedules/run-schedule', [ReportScheduleController::class, 'runScheduleNow'])->name('settings.report-schedules.run-schedule');
        
        // Logistic Companies
        Route::get('/logistics/companies', [LogisticCompanyController::class, 'index'])->name('settings.logistics.companies.index');
        Route::post('/logistics/companies', [LogisticCompanyController::class, 'store'])->name('settings.logistics.companies.store');
        Route::delete('/logistics/companies/{company}', [LogisticCompanyController::class, 'destroy'])->name('settings.logistics.companies.destroy');
        Route::put('/logistics/companies/{company}/toggle-status', [LogisticCompanyController::class, 'toggleStatus'])->name('settings.logistics.companies.toggle-status');
        
        // Drivers
        Route::get('/drivers', [DriverController::class, 'index'])->name('settings.drivers.index');
        Route::post('/drivers', [DriverController::class, 'store'])->name('settings.drivers.store');
        Route::delete('/drivers/{driver}', [DriverController::class, 'destroy'])->name('settings.drivers.destroy');
        Route::put('/drivers/{driver}/toggle-status', [DriverController::class, 'toggleStatus'])->name('settings.drivers.toggle-status');
        
        // Reorder Levels
        Route::get('/reorder-levels', [ReorderLevelController::class, 'index'])->name('settings.reorder-levels.index');
        Route::get('/reorder-levels/create', [ReorderLevelController::class, 'create'])->name('settings.reorder-levels.create');
        Route::post('/reorder-levels', [ReorderLevelController::class, 'store'])->name('settings.reorder-levels.store');
        Route::get('/reorder-levels/{reorderLevel}/edit', [ReorderLevelController::class, 'edit'])->name('settings.reorder-levels.edit');
        Route::put('/reorder-levels/{reorderLevel}', [ReorderLevelController::class, 'update'])->name('settings.reorder-levels.update');
        Route::delete('/reorder-levels/{reorderLevel}', [ReorderLevelController::class, 'destroy'])->name('settings.reorder-levels.destroy');
        Route::post('/reorder-levels/import', [ReorderLevelController::class, 'importExcel'])->name('settings.reorder-levels.import');
        Route::get('/reorder-levels/import/format', [ReorderLevelController::class, 'getImportFormat'])->name('settings.reorder-levels.import.format');
        
        // Asset Depreciation Settings
        Route::resource('asset-depreciation', AssetDepreciationSettingsController::class)->names([
            'index' => 'settings.asset-depreciation.index',
            'create' => 'settings.asset-depreciation.create',
            'store' => 'settings.asset-depreciation.store',
            'show' => 'settings.asset-depreciation.show',
            'edit' => 'settings.asset-depreciation.edit',
            'update' => 'settings.asset-depreciation.update',
            'destroy' => 'settings.asset-depreciation.destroy',
        ]);
        Route::post('asset-depreciation/{asset_depreciation}/toggle-status', [AssetDepreciationSettingsController::class, 'toggleStatus'])->name('settings.asset-depreciation.toggle-status');
        Route::post('asset-depreciation/install-defaults', [AssetDepreciationSettingsController::class, 'installDefaults'])->name('settings.asset-depreciation.install-defaults');
        Route::post('asset-depreciation/reset-to-defaults', [AssetDepreciationSettingsController::class, 'resetToDefaults'])->name('settings.asset-depreciation.reset-to-defaults');
        Route::get('asset-depreciation/export', [AssetDepreciationSettingsController::class, 'export'])->name('settings.asset-depreciation.export');

    });


require __DIR__.'/auth.php';
