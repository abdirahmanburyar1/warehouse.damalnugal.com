<?php

namespace App\Http\Controllers;

// App Models
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Facility;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\District;
use App\Models\InventoryItem;
use App\Models\InventoryAllocation;
// App Events and Resources
use App\Events\OrderEvent;
use App\Models\IssuedQuantity;
use App\Http\Resources\OrderResource;
use App\Events\InventoryUpdated;
use App\Models\Region;
use App\Models\User;
use App\Models\Driver;
use App\Models\LogisticCompany;
use App\Notifications\OrderActionRequired;

// Laravel Core
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Carbon\Carbon;
// App Facades
use App\Facades\Kafka;

class OrderController extends Controller
{
    /**
     * Reject an entire order
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectOrder(Request $request)
    {
        if (!auth()->user()->hasPermission('order-reject') && !auth()->user()->hasPermission('order-manage') && !auth()->user()->isAdmin()) {
            return response()->json('You do not have permission to reject orders', 403);
        }
        try {
            DB::beginTransaction();
            
            $request->validate([
                'order_id' => 'required|exists:orders,id',
            ]);
            
            $order = Order::findOrFail($request->order_id);
            
            // Update order status to rejected
            $order->status = 'rejected';
            $order->rejected_by = auth()->id();
            $order->rejected_at = now();
            $order->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Order has been rejected successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject order: ' . $e->getMessage()
            ], 500);
        }
    }
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('order-view') && !auth()->user()->hasPermission('order-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the orders module.');
        }
        $facility = $request->facility;
        $facilityLocation = $request->facilityLocation;
        $query = Order::query();

        if($request->filled('search')){
            $query->where('order_number', 'like', "%{$request->search}%");
        }

        if($request->filled('currentStatus')){
            $query->where('status', $request->currentStatus);
        }

        if($request->filled('dateFrom') && !$request->filled('dateTo')){
            $query->whereDate('order_date', $request->dateFrom);
        }

        if($request->filled('dateFrom') && $request->filled('dateTo')){
            $query->whereBetween('order_date', [$request->dateFrom, $request->dateTo]);
        }
        
        if($request->filled('facility')){
            $query->whereHas('facility', function($q) use ($request) {
                $q->where('name', $request->facility);
            });
        }
        
        if($request->filled('region')){
            $query->whereHas('facility', function($q) use ($request) {
                $q->where('region', $request->region);
            });
        }

        if($request->filled('district')){
            $query->whereHas('facility', function($q) use ($request) {
                $q->where('district', $request->district);
            });
        }
        
        if($request->filled('orderType') && $request->orderType !== 'All'){
            $query->where('order_type', 'like', "%{$request->orderType}%");
        }
        
        $query->with(['facility.handledby:id,name', 'user']);

        $query->orderBy('order_date', 'desc');

        $orders = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $orders->setPath(url()->current()); // Force Laravel to use full URLs
        // Get order statistics from orders table
        $stats = DB::table('orders')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status => $item->count];
            })
            ->toArray();

        // Ensure all statuses have a value
        $defaultStats = [
            'pending' => 0,
            'reviewed' => 0,
            'approved' => 0,
            'rejected' => 0,
            'in_process' => 0,
            'dispatched' => 0,
            'delivered' => 0,
            'received' => 0
        ];

        $stats = array_merge($defaultStats, $stats);

        $facilities = Facility::pluck('name')->toArray();
        $facilityLocations = District::select('id','name')->pluck('name')->toArray();
        
        return Inertia::render('Order/Index', [
            'orders' => OrderResource::collection($orders),
            'filters' => $request->only('search', 'page', 'region', 'facility', 'orderType', 'district', 'dateFrom', 'dateTo','per_page'),
            'stats' => $stats,
            'facilities' => $facilities,
            'facilityLocations' => $facilityLocations,
            'regions' => Region::pluck('name')->toArray()
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $isUpdate = !empty($request->id);
        if ($isUpdate) {
            if (!$user->hasPermission('order-edit') && !$user->hasPermission('order-manage') && !$user->isAdmin()) {
                return response()->json('You do not have permission to edit orders', 403);
            }
        } else {
            if (!$user->hasPermission('order-create') && !$user->hasPermission('order-manage') && !$user->isAdmin()) {
                return response()->json('You do not have permission to create orders', 403);
            }
        }
        try {
            $validated = $request->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'facility_id' => 'required|exists:facilities,id',
                'order_date' => 'required|date',
                'expected_date' => 'nullable|date|after_or_equal:order_date',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.id' => 'nullable|exists:order_items,id',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|numeric|min:1',
            ]);

            DB::beginTransaction();

            // Create or update order
            $order = Order::updateOrCreate(
                ['id' => $request->id],
                [
                    'warehouse_id' => $validated['warehouse_id'],
                    'facility_id' => $validated['facility_id'],
                    'user_id' => auth()->id(),
                    'order_number' => $request->id ? Order::find($request->id)->order_number : 'ORD-' . strtoupper(uniqid()),
                    'status' => $request->id ? Order::find($request->id)->status : 'pending',
                    'number_items' => collect($validated['items'])->sum('quantity'),
                    'notes' => $validated['notes'] ?? null,
                    'order_date' => $validated['order_date'],
                    'expected_date' => $validated['expected_date'] ?? null,
                ]
            );

            // Get current item IDs
            $currentItemIds = collect($validated['items'])
                ->pluck('id')
                ->filter()
                ->toArray();

            // Delete items that are not in the request (only pending orders)
            if ($request->id) {
                $order->items()
                    ->whereNotIn('id', $currentItemIds)
                    ->where('order_id', $order->id)
                    ->delete();
            }

            // Process each item
            foreach ($validated['items'] as $itemData) {
                if (!empty($itemData['id'])) {
                    // Update existing item
                    $order->items()
                        ->where('id', $itemData['id'])
                        ->update([
                            'product_id' => $itemData['product_id'],
                            'quantity' => $itemData['quantity'],
                        ]);
                } else {
                    // Create new item
                    $order->items()->create([
                        'product_id' => $itemData['product_id'],
                        'quantity' => $itemData['quantity']
                    ]);
                }
            }

            Kafka::publishOrderPlaced('Refreshed');

            event(new OrderEvent('Refreshed'));

            // Workflow: on new order creation, notify users with order-review (next action = review)
            if (!$request->id) {
                $reviewers = User::withPermission('order-review')
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->where('id', '!=', auth()->id())
                    ->get();
                foreach ($reviewers as $reviewer) {
                    $reviewer->notify(new OrderActionRequired($order, OrderActionRequired::ACTION_NEEDS_REVIEW));
                }
            }

            DB::commit();
            return response()->json('Order ' . ($request->id ? 'updated' : 'created') . ' successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }


    public function dispatchInfo(Request $request){
        if (!auth()->user()->hasPermission('order-manage') && !auth()->user()->hasPermission('order-dispatch') && !auth()->user()->isAdmin()) {
            return response()->json('You do not have permission to dispatch orders', 403);
        }
        try {
            return DB::transaction(function() use ($request){
                $request->validate([
                    'dispatch_date' => 'required|date',
                    'driver_id' => 'required|exists:drivers,id',
                    'driver_number' => 'required|string',
                    'plate_number' => 'required|string',
                    'no_of_cartoons' => 'required|numeric',
                    'order_id' => 'required|exists:orders,id',
                    'logistic_company_id' => 'required|exists:logistic_companies,id',
                    'status' => 'required|string'
                ]);

                $order = Order::with('dispatch')->find($request->order_id);
                $order->dispatch()->create([
                    'order_id' => $request->order_id,
                    'dispatch_date' => $request->dispatch_date,
                    'driver_id' => $request->driver_id,
                    'logistic_company_id' => $request->logistic_company_id,
                    'driver_number' => $request->driver_number,
                    'plate_number' => $request->plate_number,
                    'no_of_cartoons' => $request->no_of_cartoons,
                ]);

                $order->status = $request->status;
                $order->dispatched_at = now();
                $order->dispatched_by = auth()->user()->id;
                $order->save();
                
                return response()->json("Dispatched Successfully", 200);
            });
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    
    public function destroy(Order $order)
    {
        if (!auth()->user()->hasPermission('order-delete') && !auth()->user()->hasPermission('order-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to delete orders.');
        }
        try {
            if ($order->status !== 'pending') {
                return back()->with('error', 'Only pending orders can be deleted.');
            }

            $order->items()->delete();
            $order->delete();

            return back()->with('success', 'Order deleted successfully.');
        } catch (\Throwable $th) {
            return back()->with($th->getMessage(), 500);
        }
    }

    public function bulk(Request $request)
    {
        if (!auth()->user()->hasPermission('order-delete') && !auth()->user()->hasPermission('order-manage') && !auth()->user()->isAdmin()) {
            return response()->json('You do not have permission to delete orders', 403);
        }
        try {
            $orderIds = $request->input('orderIds');

            // Validate that at least one order is selected
            if (empty($orderIds)) {
                return response()->json('Please select at least one order', 400);
            }

            // Get all selected orders
            $orders = Order::whereIn('id', $orderIds)->get();

            // Check if any order has non-pending items and collect their IDs
            $nonPendingOrders = [];
            foreach ($orders as $order) {
                if ($order->status !== 'pending') {
                    $nonPendingOrders[] = $order->id;
                }
            }

            if (!empty($nonPendingOrders)) {
                return response()->json('Cannot delete orders that are not in pending status', 500);
            }

            // Delete orders if all are pending
            $orders->each(function ($order) {
                $order->items()->delete();
                $order->delete();
            });

            return response()->json('Selected orders deleted successfully', 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    // updateQuantity
    public function updateQuantity(Request $request)
    {
        if (!auth()->user()->hasPermission('order-edit') && !auth()->user()->hasPermission('order-manage') && !auth()->user()->isAdmin()) {
            return response()->json('You do not have permission to update order quantities', 403);
        }
        try {
            DB::beginTransaction();
            
            // Log the incoming request for debugging
            logger()->info('UpdateQuantity Request Data:', $request->all());
    
            // Check if the OrderItem exists manually to provide better error info
            $orderItemExists = \App\Models\OrderItem::where('id', $request->item_id)->exists();
            
            $request->validate([
                'item_id'  => 'required|exists:order_items,id',
                'quantity' => 'required|numeric',
                'days'     => 'required|numeric|min:1',
                'type'     => 'required|in:quantity_to_release,days',
            ], [
                'days.min' => 'No. of days cannot be 0. Please enter at least 1.',
            ]);
    

            $orderItem = OrderItem::find($request->item_id);
            
            if (!$orderItem) {
                logger()->error('OrderItem not found:', ['item_id' => $request->item_id]);
                return response()->json(['error' => 'Order item not found'], 404);
            }
            
            $order = $orderItem->order;
            

            if($orderItem->quantity <= 0) {
                $orderItem->quantity = $request->quantity;
                $orderItem->save();
                $orderItem->refresh();
            }
    
            if (!in_array($order->status, ['pending'])) {
                return response()->json('Cannot update quantity for orders that are not in pending status', 500);
            }
    
            // Handle original quantity fallback
            $originalQuantity = $orderItem->quantity > 0 ? $orderItem->quantity : $request->quantity;
            $originalDays = $orderItem->no_of_days > 0 ? $orderItem->no_of_days : 1;
            $dailyUsageRate = $originalQuantity / $originalDays;
            
    
            // Calculate new quantity and days (table column is no_of_days)
            if ($request->type === 'days') {
                $newDays = (int) ceil($request->days);
                $newQuantityToRelease = (int) ceil($dailyUsageRate * $newDays);
                $orderItem->no_of_days = $newDays;
            } else {
                $newQuantityToRelease = (int) ceil($request->quantity);
                $newDays = (int) ceil($dailyUsageRate > 0 ? ($newQuantityToRelease / $dailyUsageRate) : 1);
                $orderItem->no_of_days = $newDays;
            }
    
            $oldQuantityToRelease = $orderItem->quantity_to_release ?? 0;
            
            // Case 1: Decrease
            if ($newQuantityToRelease < $oldQuantityToRelease) {
                $quantityToRemove = $oldQuantityToRelease - $newQuantityToRelease;
                $remainingToRemove = $quantityToRemove;
    
                $allocations = $orderItem->inventory_allocations()->orderBy('expiry_date', 'desc')->get();
    
                foreach ($allocations as $allocation) {
                    if ($remainingToRemove <= 0) break;
    
                    $inventory = InventoryItem::where('product_id', $allocation->product_id)
                        ->where('warehouse_id', $allocation->warehouse_id)
                        ->where('batch_number', $allocation->batch_number)
                        ->where('expiry_date', $allocation->expiry_date)
                        ->first();
    
                    $restoreQty = min($allocation->allocated_quantity, $remainingToRemove);
    
                    if ($inventory) {
                        $inventory->quantity += $restoreQty;
                        $inventory->save();
                    } else {
                        // Find or create parent inventory record (only by product_id)
                        $parentInventory = \App\Models\Inventory::firstOrCreate([
                            'product_id' => $allocation->product_id,
                        ], [
                            'quantity' => 0, // Will be updated by the inventory item
                        ]);

                        if ($parentInventory->wasRecentlyCreated) {
                            logger()->info("Created parent inventory record during order update", [
                                'product_id' => $allocation->product_id,
                                'inventory_id' => $parentInventory->id
                            ]);
                        }

                        InventoryItem::create([
                            'inventory_id' => $parentInventory->id,
                            'product_id'   => $allocation->product_id,
                            'warehouse_id' => $allocation->warehouse_id,
                            'location'     => $allocation->location,
                            'batch_number' => $allocation->batch_number,
                            'uom'          => $allocation->uom,
                            'barcode'      => $allocation->barcode,
                            'expiry_date'  => $allocation->expiry_date,
                            'quantity'     => $restoreQty
                        ]);

                        // Update parent inventory quantity
                        $parentInventory->increment('quantity', $restoreQty);
                    }
    
                    if ($allocation->allocated_quantity <= $remainingToRemove) {
                        $remainingToRemove -= $allocation->allocated_quantity;
                        $allocation->delete();
                    } else {
                        $allocation->allocated_quantity -= $remainingToRemove;
                        $allocation->save();
                        $remainingToRemove = 0;
                    }
                }
    
                $orderItem->quantity_to_release = $newQuantityToRelease;
                $orderItem->save();
    
                DB::commit();
                return response()->json('Quantity to release decreased successfully', 200);
            }
    
            // Case 2: Increase
            if ($newQuantityToRelease > $oldQuantityToRelease) {
                $quantityToAdd = $newQuantityToRelease - $oldQuantityToRelease;
                $remainingToAllocate = $quantityToAdd;
                
                $inventoryItems = InventoryItem::where('product_id', $orderItem->product_id)
                    ->where('quantity', '>', 0)
                    ->orderBy('expiry_date', 'asc')
                    ->get();
                    

    
                if ($inventoryItems->isEmpty()) {
                    DB::rollBack();
                    return response()->json('No inventory available for this product', 500);
                }
    
                foreach ($inventoryItems as $inventory) {
                    if ($remainingToAllocate <= 0) break;
    
                    $allocQty = min($inventory->quantity, $remainingToAllocate);
                    
    
    
                    $existingAllocation = $orderItem->inventory_allocations()
                        ->where('batch_number', $inventory->batch_number)
                        ->where('expiry_date', $inventory->expiry_date)
                        ->first();
    
                    if ($existingAllocation) {
                       
                        $existingAllocation->allocated_quantity += $allocQty;
                        $existingAllocation->save();
                    } else {
                       
                        $orderItem->inventory_allocations()->create([
                            'product_id'       => $inventory->product_id,
                            'warehouse_id'     => $inventory->warehouse_id,
                            'location'         => $inventory->location,
                            'batch_number'     => $inventory->batch_number,
                            'uom'              => $inventory->uom,
                            'barcode'          => $inventory->barcode ?? null,
                            'expiry_date'      => $inventory->expiry_date,
                            'allocated_quantity' => $allocQty,
                            'allocation_type'  => $order->order_type,
                            'unit_cost'        => $inventory->unit_cost,
                            'total_cost'       => $inventory->unit_cost * $allocQty,
                            'source'           => $inventory->source ?? 'warehouse', // Capture source from inventory_items
                            'notes'            => 'Allocated from inventory ID: ' . $inventory->id
                        ]);
                    }
    
                    // Update inventory quantity and clean up empty batches
                    $newQuantity = $inventory->quantity - $allocQty;
                    if ($newQuantity <= 0) {
                        // Delete the inventory item if quantity becomes zero or negative
                        $inventory->delete();
                        logger()->info("Deleted empty batch during order update", [
                            'batch_number' => $inventory->batch_number,
                            'product_id' => $inventory->product_id,
                            'order_item_id' => $orderItem->id
                        ]);
                    } else {
                        $inventory->quantity = $newQuantity;
                        $inventory->save();
                    }
                    $remainingToAllocate -= $allocQty;
                    
                   
                }
    
                // Final adjustment
                $totalAllocated = $orderItem->inventory_allocations()->sum('allocated_quantity');
                if ($totalAllocated < $newQuantityToRelease) {
                    $difference = $newQuantityToRelease - $totalAllocated;
                    $lastAllocation = $orderItem->inventory_allocations()->latest()->first();
    
                    if ($lastAllocation) {
                        $lastAllocation->allocated_quantity += $difference;
                        $lastAllocation->save();
    
                        $inventory = InventoryItem::where('product_id', $lastAllocation->product_id)
                            ->where('warehouse_id', $lastAllocation->warehouse_id)
                            ->where('batch_number', $lastAllocation->batch_number)
                            ->where('expiry_date', $lastAllocation->expiry_date)
                            ->first();
    
                        if ($inventory) {
                            $newQuantity = $inventory->quantity - $difference;
                            if ($newQuantity <= 0) {
                                // Delete the inventory item if quantity becomes zero or negative
                                $inventory->delete();
                                logger()->info("Deleted empty batch during final adjustment", [
                                    'batch_number' => $inventory->batch_number,
                                    'product_id' => $inventory->product_id,
                                    'order_item_id' => $orderItem->id
                                ]);
                            } else {
                                $inventory->quantity = $newQuantity;
                                $inventory->save();
                            }
                        }
                    }
                }
    
                if ($remainingToAllocate > 0) {
                   
                    DB::rollBack();
                    return response()->json('Insufficient inventory. Could only allocate ' . ($quantityToAdd - $remainingToAllocate) . ' out of ' . $quantityToAdd, 500);
                }
    
                $orderItem->quantity_to_release = $newQuantityToRelease;
                $orderItem->save();
                
               
    
                // event(new InventoryUpdated());
    
                DB::commit();
                return response()->json('Quantity to release updated successfully', 200);
            }
    
            // Case 3: Always recalculate and reallocate quantities
           
            
            // Clear all existing allocations and restore inventory
            $existingAllocations = $orderItem->inventory_allocations()->get();
            
            foreach ($existingAllocations as $allocation) {
                // Restore inventory
                $inventory = InventoryItem::where('product_id', $allocation->product_id)
                    ->where('warehouse_id', $allocation->warehouse_id)
                    ->where('batch_number', $allocation->batch_number)
                    ->where('expiry_date', $allocation->expiry_date)
                    ->first();
                
                if ($inventory) {
                    $inventory->quantity += $allocation->allocated_quantity;
                    $inventory->save();
                } else {
                    // Find or create parent inventory record (only by product_id)
                    $parentInventory = \App\Models\Inventory::firstOrCreate([
                        'product_id' => $allocation->product_id,
                    ], [
                        'quantity' => 0, // Will be updated by the inventory item
                    ]);

                    if ($parentInventory->wasRecentlyCreated) {
                        logger()->info("Created parent inventory record during allocation restore", [
                            'product_id' => $allocation->product_id,
                            'inventory_id' => $parentInventory->id
                        ]);
                    }

                    InventoryItem::create([
                        'inventory_id' => $parentInventory->id,
                        'product_id'   => $allocation->product_id,
                        'warehouse_id' => $allocation->warehouse_id,
                        'location'     => $allocation->location,
                        'batch_number' => $allocation->batch_number,
                        'uom'          => $allocation->uom,
                        'barcode'      => $allocation->barcode,
                        'expiry_date'  => $allocation->expiry_date,
                        'quantity'     => $allocation->allocated_quantity
                    ]);

                    // Update parent inventory quantity
                    $parentInventory->increment('quantity', $allocation->allocated_quantity);
                }
                
               
                
                $allocation->delete();
            }
            
            // Now create fresh allocations for the required quantity
            if ($newQuantityToRelease > 0) {
                $remainingToAllocate = $newQuantityToRelease;
                
                $inventoryItems = InventoryItem::where('product_id', $orderItem->product_id)
                    ->where('quantity', '>', 0)
                    ->orderBy('expiry_date', 'asc')
                    ->get();
                    
               
                
                if ($inventoryItems->isEmpty()) {
                    DB::rollBack();
                    return response()->json('No inventory available for this product', 500);
                }
                
                foreach ($inventoryItems as $inventory) {
                    if ($remainingToAllocate <= 0) break;
                    
                    $allocQty = min($inventory->quantity, $remainingToAllocate);
                    
                   
                    
                    $orderItem->inventory_allocations()->create([
                        'product_id'       => $inventory->product_id,
                        'warehouse_id'     => $inventory->warehouse_id,
                        'location'         => $inventory->location,
                        'batch_number'     => $inventory->batch_number,
                        'uom'              => $inventory->uom,
                        'barcode'          => $inventory->barcode ?? null,
                        'expiry_date'      => $inventory->expiry_date,
                        'allocated_quantity' => $allocQty,
                        'allocation_type'  => $order->order_type,
                        'unit_cost'        => $inventory->unit_cost,
                        'total_cost'       => $inventory->unit_cost * $allocQty,
                        'source'           => $inventory->source ?? 'warehouse', // Capture source from inventory_items
                        'notes'            => 'Fresh allocation from inventory ID: ' . $inventory->id
                    ]);
                    
                    $inventory->quantity -= $allocQty;
                    $inventory->save();
                    $remainingToAllocate -= $allocQty;
                    
                   
                }
                
                if ($remainingToAllocate > 0) {
                    DB::rollBack();
                    return response()->json('Insufficient inventory. Could only allocate ' . ($newQuantityToRelease - $remainingToAllocate) . ' out of ' . $newQuantityToRelease, 500);
                }
            }
            
            $orderItem->quantity_to_release = $newQuantityToRelease;
            $orderItem->save();           
            
            // event(new InventoryUpdated());
            
            DB::commit();
            return response()->json('Quantities recalculated and allocated successfully', 200);
    
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }
    
    
    public function searchProduct(Request $request)
    {
        try {
            $search = $request->input('search');
            $products = Product::where('name', 'like', '%' . $search . '%')
                ->select('id', 'name')
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                    ];
                });

            return response()->json($products, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function changeStatus(Request $request)
    {
        $user = auth()->user();
        if (!auth()->user()->hasPermission('order-view') && !auth()->user()->hasPermission('order-manage') && !$user->isAdmin()) {
            return response()->json('You do not have permission to access orders', 403);
        }
        try {
            DB::beginTransaction();
            // Validate request
            $validated = $request->validate([
                'order_id' => 'required|exists:orders,id',
                'status' => ['required', Rule::in(['reviewed','approved', 'in_process', 'dispatched','rejected'])]
            ]);

            $order = Order::with('items.inventory_allocations')->find($request->order_id);

            // Define allowed transitions
            $allowedTransitions = [
                'pending' => ['reviewed', 'rejected'],
                'reviewed' => ['approved', 'rejected'],
                'approved' => ['in_process'],
                'in_process' => ['dispatched'],
                'rejected' => ['approved'] // Allow rejected orders to be approved
            ];

            // Check if the transition is allowed
            if (
                !isset($allowedTransitions[$order->status]) ||
                !in_array($request->status, $allowedTransitions[$order->status])
            ) {
                return response()->json("Status transition not allowed", 500);
            }

            // Workflow permission checks (same pattern as transfer)
            $newStatus = $request->status;
            if ($newStatus === 'reviewed') {
                if (!$user->hasPermission('order-review') && !$user->hasPermission('order-manage') && !$user->isAdmin()) {
                    DB::rollBack();
                    return response()->json('You do not have permission to review orders', 403);
                }
            } elseif ($newStatus === 'approved') {
                if (!$user->hasPermission('order-approve') && !$user->hasPermission('order-manage') && !$user->isAdmin()) {
                    DB::rollBack();
                    return response()->json('You do not have permission to approve orders', 403);
                }
            } elseif ($newStatus === 'rejected') {
                if (!$user->hasPermission('order-reject') && !$user->hasPermission('order-manage') && !$user->isAdmin()) {
                    DB::rollBack();
                    return response()->json('You do not have permission to reject orders', 403);
                }
            } elseif ($newStatus === 'in_process') {
                if (!$user->hasPermission('order-processing') && !$user->hasPermission('order-manage') && !$user->isAdmin()) {
                    DB::rollBack();
                    return response()->json('You do not have permission to process orders', 403);
                }
            } elseif ($newStatus === 'dispatched') {
                if (!$user->hasPermission('order-dispatch') && !$user->hasPermission('order-manage') && !$user->isAdmin()) {
                    DB::rollBack();
                    return response()->json('You do not have permission to dispatch orders', 403);
                }
            }

            $userId = auth()->id();
            $now = now();

            // Prepare updates for order
            $updates = ['status' => $request->status];

            // Add timestamp based on the status
            switch ($request->status) {
                case 'reviewed':
                    $updates['reviewed_at'] = $now;
                    $updates['reviewed_by'] = $userId;
                    break;
                case 'approved':
                    $updates['approved_at'] = $now;
                    $updates['approved_by'] = $userId;
                    
                    // If transitioning from rejected to approved, clear rejection data
                    if ($order->status === 'rejected') {
                        $updates['rejected_by'] = null;
                        $updates['rejected_at'] = null;
                    }
                    if($order->status === 'rejected'){

                        
                        // $updates['rejected_by'] = null;
                        // $updates['rejected_at'] = null;
                    }
                    // issued quantity - history
                   foreach($order->items as $item){
                        foreach($item['inventory_allocations'] as $allocation){
                            // Barcode is required in issued_quantities; fall back to inventory item if allocation barcode is missing (products table has no barcode column)
                            $barcode = $allocation['barcode'] ?? null;
                            if (empty($barcode)) {
                                $barcode = InventoryItem::where('product_id', $allocation['product_id'])
                                    ->where('warehouse_id', $allocation['warehouse_id'])
                                    ->where('batch_number', $allocation['batch_number'])
                                    ->where('expiry_date', $allocation['expiry_date'])
                                    ->value('barcode');
                            }

                            $issuedPayload = [
                                'product_id' => $allocation['product_id'],
                                'warehouse_id' => $allocation['warehouse_id'],
                                'quantity' => $allocation['allocated_quantity'],
                                'batch_number' => $allocation['batch_number'],
                                'barcode' => $barcode ?: '',
                                'uom' => $allocation['uom'],
                                'expiry_date' => $allocation['expiry_date'],
                                'issued_by' => $userId,
                                'issued_date' => $now,
                                'unit_cost' => $allocation['unit_cost'] ?? 0,
                                'total_cost' => $allocation['total_cost'] ?? 0,
                            ];

                            // Only include foreign keys if columns exist in DB schema
                            if (Schema::hasColumn('issued_quantities', 'order_id')) {
                                $issuedPayload['order_id'] = $order->id;
                            }
                            if (Schema::hasColumn('issued_quantities', 'transfer_id')) {
                                $issuedPayload['transfer_id'] = null;
                            }

                            IssuedQuantity::create($issuedPayload);
                        }
                    }
                    break;
                case 'rejected':
                    $updates['rejected_at'] = $now;
                    $updates['rejected_by'] = $userId;
                    
                    // Rollback inventory allocations back to inventory
                    foreach($order->items as $item) {
                        foreach($item->inventory_allocations as $allocation) {
                            // Find the corresponding inventory item
                            $inventoryItem = InventoryItem::where('product_id', $allocation->product_id)
                                ->where('warehouse_id', $allocation->warehouse_id)
                                ->where('batch_number', $allocation->batch_number)
                                ->where('expiry_date', $allocation->expiry_date)
                                ->first();
                            
                            if ($inventoryItem) {
                                // Restore the quantity back to inventory
                                $inventoryItem->quantity += $allocation->allocated_quantity;
                                $inventoryItem->save();
                            } else {
                                // Create new inventory item if it doesn't exist
                                InventoryItem::create([
                                    'product_id' => $allocation->product_id,
                                    'warehouse_id' => $allocation->warehouse_id,
                                    'location' => $allocation->location,
                                    'batch_number' => $allocation->batch_number,
                                    'uom' => $allocation->uom,
                                    'barcode' => $allocation->barcode,
                                    'expiry_date' => $allocation->expiry_date,
                                    'quantity' => $allocation->allocated_quantity,
                                    'unit_cost' => $allocation->unit_cost ?? 0,
                                    'total_cost' => $allocation->total_cost ?? 0,
                                    'notes' => 'Restored from rejected order allocation'
                                ]);
                            }
                        }
                        
                        // Delete all inventory allocations for this order item
                        $item->inventory_allocations()->delete();
                    }
                    break;
                case 'in_process':
                    $updates['status'] = 'in_process';
                    $updates['processed_at'] = $now;
                    $updates['processed_by'] = $userId;
                    break;
                case 'dispatched':
                    $updates['dispatched_by'] = $userId;
                    $updates['dispatched_at'] = $now;
                    break;
            }

            // Update the order
            $order->update($updates);

            // Workflow: notify eligible users (by permission) for the next action
            $notifyRecipients = function (string $permission, string $actionConstant) use ($user, $order) {
                User::withPermission($permission)
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->where('id', '!=', $user->id)
                    ->get()
                    ->each(fn ($u) => $u->notify(new OrderActionRequired($order, $actionConstant)));
            };
            $notifyRecipientsMultiple = function (array $permissions, string $actionConstant) use ($user, $order) {
                $recipients = collect();
                foreach ($permissions as $perm) {
                    $recipients = $recipients->merge(
                        User::withPermission($perm)->where('is_active', true)->whereNotNull('email')->where('id', '!=', $user->id)->get()
                    );
                }
                $recipients->unique('id')->each(fn ($u) => $u->notify(new OrderActionRequired($order, $actionConstant)));
            };

            if ($newStatus === 'reviewed') {
                $notifyRecipientsMultiple(['order-approve', 'order-reject'], OrderActionRequired::ACTION_READY_FOR_APPROVAL);
            } elseif ($newStatus === 'approved') {
                $notifyRecipients('order-processing', OrderActionRequired::ACTION_READY_FOR_PROCESSING);
            } elseif ($newStatus === 'in_process') {
                $notifyRecipients('order-dispatch', OrderActionRequired::ACTION_READY_FOR_DISPATCH);
            }

            DB::commit();
            return response()->json('Order status updated successfully.', 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json('Failed to update order status: ' . $e->getMessage(), 500);
        }
    }

    public function getOutstanding(Request $request, $id)
    {
        try {
            $outstanding = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('facilities', 'facilities.id', '=', 'orders.facility_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->where('order_items.id', $id)
                ->whereNotIn('order_items.status', ['pending'])
                ->select(
                    'products.name as product_name',
                    'facilities.name as facility_name',
                    'order_items.quantity',
                    'order_items.status'
                )
                ->get()
                ->map(function ($item) {
                    return [
                        'product' => $item->product_name,
                        'facility' => $item->facility_name,
                        'quantity' => $item->quantity,
                        'status' => $item->status
                    ];
                });

            return response()->json($outstanding, 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function updateItem(Request $request)
    {
        if (!auth()->user()->hasPermission('order-edit') && !auth()->user()->hasPermission('order-manage') && !auth()->user()->isAdmin()) {
            return response()->json('You do not have permission to update order items', 403);
        }
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'id' => 'required|exists:order_items,id',
                'quantity' => 'required|integer|min:1',
            ]);

            $orderItem = OrderItem::findOrFail($validated['id']);

            // Check if the new quantity will not exceed the current inventory quantity
            $currentInventoryQuantity = \App\Models\Inventory::where('product_id', $orderItem->product_id)->sum('quantity');
            if ($validated['quantity'] > $currentInventoryQuantity) {
                return response()->json('The new quantity exceeds the current inventory quantity.', 500);
            }

            $orderItem->update([
                'quantity' => $validated['quantity'],
            ]);
            Kafka::publishOrderPlaced("Refreshed");
            event(new OrderEvent('Refreshed'));

            DB::commit();
            return response()->json('Order item updated successfully.', 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * Bulk change status of orders
     */
    public function bulkChangeStatus(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'required|exists:orders,id',
            'status' => ['required', Rule::in(['approved', 'in process', 'dispatched', 'delivery_pending', 'delivered'])]
        ]);

        $allowedTransitions = [
            'pending' => ['approved'],
            'approved' => ['in process'],
            'in process' => ['dispatched'],
            'dispatched' => ['delivery_pending', 'delivered'],
            'delivery_pending' => ['delivered']
        ];

        DB::beginTransaction();
        try {
            $orders = Order::whereIn('id', $request->order_ids)->get();
            $updatedCount = 0;

            foreach ($orders as $order) {
                if (
                    isset($allowedTransitions[$order->status]) &&
                    in_array($request->status, $allowedTransitions[$order->status])
                ) {

                    $oldStatus = $order->status;
                    $order->status = $request->status;
                    $order->save();


                    $updatedCount++;
                }
            }

            DB::commit();

            if ($updatedCount === 0) {
                return response()->json("No orders were eligible for status change", 500);
            }

            Kafka::publishOrderPlaced('Refreshed');
            event(new OrderEvent('Order status updated'));
            return response()->json("Successfully updated {$updatedCount} orders to {$request->status}");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk order status change failed', [
                'error' => $e->getMessage(),
                'order_ids' => $request->order_ids
            ]);
            return response()->json('Failed to update order statuses: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk change status of order items
     */
    public function bulkChangeItemStatus(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'item_ids' => 'required|array',
                'item_ids.*' => 'required|exists:order_items,id',
                'status' => ['required', Rule::in(['approved', 'in process', 'dispatched', 'delivery_pending', 'delivered'])],
                'warehouse_id' => 'nullable|exists:warehouses,id'
            ]);

            $items = OrderItem::with('order')->whereIn('id', $request->item_ids)->get();
            $updatedCount = 0;
            $updatedOrders = [];

            $allowedTransitions = [
                'pending' => ['approved'],
                'approved' => ['in process'],
                'in process' => ['dispatched'],
                'dispatched' => ['delivery_pending', 'delivered'],
                'delivery_pending' => ['delivered']
            ];


            foreach ($items as $item) {
                // Check if transition is allowed
                if (
                    !isset($allowedTransitions[$item->status]) ||
                    !in_array($request->status, $allowedTransitions[$item->status])
                ) {
                    continue;
                }

                $oldStatus = $item->status;
                $item->status = $request->status;
                // Get all available inventory for this product from the warehouse, ordered by expiry date (FIFO)
                $warehouseInventories = Inventory::where('product_id', $item->product_id)
                    ->where('quantity', '>', 0)
                    // ->where('warehouse_id', $item->warehouse_id)
                    ->orderBy('expiry_date', 'asc')  // Order by expiry date for FIFO (oldest first)
                    ->get();

                $remainingQuantity = (float) $item->quantity - (float) $item->quantity_on_order;

                if ($warehouseInventories->sum('quantity') < $remainingQuantity) {
                    return response()->json("Not enough items in the inventory", 500);
                }
                if ($request->status == 'approved') {
                    $item->approved_at = Carbon::now()->toDateString();
                    $item->approved_by = auth()->id();
                    $item->save();
                }

                if ($request->status == 'in process') {
                    $item->in_process = 1;
                    $item->save();
                }

                if ($request->status == 'dispatched') {
                    $item->dispatched_at = Carbon::now()->toDateString();
                    $item->dispatched_by = auth()->id();
                    $item->warehouse_id = $request->warehouse_id;
                    $item->save();
                }
                if ($request->status == 'delivery_pending') {
                    $item->delivery_pending_at = Carbon::now()->toDateString();
                    $item->save();
                }
                if ($request->status == 'delivered') {
                    $item->delivered = 1;

                    $usedInventories = [];

                    foreach ($warehouseInventories as $warehouseInventory) {
                        if ($remainingQuantity <= 0) break;

                        // Calculate how much we can take from this batch
                        $quantityToTake = min($remainingQuantity, $warehouseInventory->quantity);

                        // Update or create facility inventory for this batch
                        $facilityInventory = $item->order->facility->inventories()
                            ->where('product_id', $item->product_id)
                            ->where('batch_number', $warehouseInventory->batch_number)
                            ->first();

                        if ($facilityInventory) {
                            $facilityInventory->increment('quantity', $quantityToTake);
                            // Handle facility inventory with quantity 0
                            if ($facilityInventory->fresh()->quantity <= 0) {
                                // Get all zero quantity records for this product
                                $zeroQuantityInventories = $item->order->facility
                                    ->inventories()
                                    ->where('product_id', $item->product_id)
                                    ->where('quantity', '=', 0)
                                    ->orderBy('created_at', 'asc')  // Get oldest first
                                    ->get();

                                if ($zeroQuantityInventories->count() > 1) {
                                    // Keep the first (oldest) record and reset its metadata
                                    $firstRecord = $zeroQuantityInventories->first();
                                    $firstRecord->update([
                                        'batch_number' => null,
                                        'expiry_date' => null,
                                        'location' => null,
                                        'warehouse_id' => null
                                    ]);

                                    // Delete all other zero quantity records
                                    $item->order->facility->inventories()
                                        ->where('product_id', $item->product_id)
                                        ->where('quantity', '=', 0)
                                        ->where('id', '!=', $firstRecord->id)
                                        ->delete();
                                } else {
                                    // If this is the only zero quantity record, just reset its metadata
                                    $facilityInventory->update([
                                        'batch_number' => null,
                                        'expiry_date' => null,
                                        'location' => null,
                                        'warehouse_id' => null
                                    ]);
                                }
                            }
                        } else {
                            $item->order->facility->inventories()->create([
                                'product_id' => $item->product_id,
                                'batch_number' => $warehouseInventory->batch_number,
                                'expiry_date' => $warehouseInventory->expiry_date,
                                'quantity' => $quantityToTake,
                                'updated_at' => now()
                            ]);
                        }
                        // here we gonna update the inventories table
                        $warehouseInventory->decrement('quantity', $quantityToTake);

                        // Remove inventory record if quantity is 0
                        if ($warehouseInventory->fresh()->quantity <= 0) {
                            $warehouseInventory->delete();
                        }

                        // Track used inventory for logging
                        $usedInventories[] = [
                            'batch_number' => $warehouseInventory->batch_number,
                            'expiry_date' => $warehouseInventory->expiry_date->format('Y-m-d'),
                            'quantity' => $quantityToTake
                        ];

                        $remainingQuantity -= $quantityToTake;
                    }

                    // Check if all items in this order have the same status
                    $pendingItems = $item->order->items()
                        ->where('status', '!=', 'delivered')
                        ->count();

                    if ($pendingItems === 0) {
                        $item->order->status = 'completed';
                        $item->order->save();
                    }

                    $item->delivered = 1;
                    $item->status = 'delivered';
                    $item->save();
                }

                $zeroQuantityInventories = Inventory::where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->where('quantity', '=', 0)
                    ->get();

                if ($zeroQuantityInventories->count() > 1) {
                    // Keep the oldest record and reset its metadata
                    $oldestRecord = $zeroQuantityInventories->sortBy('created_at')->first();
                    $oldestRecord->update([
                        'batch_number' => null,
                        'expiry_date' => null,
                        'location' => null,
                        'warehouse_id' => null
                    ]);

                    // Delete all other zero quantity records except the oldest
                    Inventory::where('product_id', $item->product_id)
                        ->where('warehouse_id', $item->warehouse_id)
                        ->where('quantity', '=', 0)
                        ->where('id', '!=', $oldestRecord->id)
                        ->delete();
                } elseif ($zeroQuantityInventories->count() == 1) {
                    // If only one record exists, just reset its metadata
                    $zeroQuantityInventories->first()->update([
                        'batch_number' => null,
                        'expiry_date' => null,
                        'location' => null,
                        'warehouse_id' => null
                    ]);
                }

                // Broadcast event
                Kafka::publishOrderPlaced('Refreshed');
                event(new OrderEvent('Refreshed'));

                // Track unique orders that were affected
                if (!in_array($item->order_id, $updatedOrders)) {
                    $updatedOrders[] = $item->order_id;

                    // Check if all items in this order have the same status
                    // $allItemsSameStatus = $item->order->items()
                    //     ->where('status', '!=', $request->status)
                    //     ->doesntExist();

                    // if ($allItemsSameStatus) {
                    //     $item->order->status = "completed";
                    //     $item->order->save();
                    // }

                }

                $updatedCount++;
            }

            DB::commit();

            if ($updatedCount === 0) {
                return response()->json("No items were eligible for status change. Please check if the status transitions are allowed.", 500);
            }

            Kafka::publishOrderPlaced('Refreshed');

            event(new OrderEvent('Order items status updated'));
            return response()->json("Successfully updated {$updatedCount} items to {$request->status}");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk order item status change failed', [
                'error' => $e->getMessage(),
                'item_ids' => $request->item_ids
            ]);
            return response()->json('Failed to update item statuses: ' . $e->getMessage(), 500);
        }
    }

    public function changeItemStatus(Request $request)
    {
        try {
            DB::beginTransaction();
    
            $request->validate([
                'item_id' => 'required|exists:order_items,id',
                'status' => ['required', Rule::in(['approved', 'in process', 'dispatched', 'delivery_pending', 'delivered'])],
                'warehouse_id' => 'nullable|exists:warehouses,id'
            ]);
    
            $item = OrderItem::with(['order.facility', 'product'])->findOrFail($request->item_id);
    
            $allowedTransitions = [
                'pending' => ['approved'],
                'approved' => ['in process'],
                'in process' => ['dispatched'],
                'dispatched' => ['delivery_pending', 'delivered'],
                'delivery_pending' => ['delivered']
            ];
    
            if (!isset($allowedTransitions[$item->status]) || !in_array($request->status, $allowedTransitions[$item->status])) {
                return response()->json("Status transition not allowed", 500);
            }
    
            $remainingQuantity = (float)$item->quantity - (float)$item->quantity_on_order;
    
            // Get all InventoryItems (not just Inventory) ordered by expiry
            $inventoryItems = InventoryItem::where('product_id', $item->product_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->where('quantity', '>', 0)
                ->orderBy('expiry_date', 'asc')
                ->get();
    
            if ($inventoryItems->sum('quantity') < $remainingQuantity) {
                return response()->json("Not enough stock to fulfill {$item->quantity} units for product: {$item->product->name}", 500);
            }
    
            // === Status Updates ===
            if ($request->status === 'approved') {
                $item->approved_at = now();
                $item->approved_by = auth()->id();
            }
    
            if ($request->status === 'in process') {
                $item->in_process = true;
            }
    
            if ($request->status === 'dispatched') {
                $item->dispatched_at = now();
                $item->dispatched_by = auth()->id();
                $item->warehouse_id = $request->warehouse_id;
            }
    
            if ($request->status === 'delivery_pending') {
                $item->delivery_pending_at = now();
            }
    
            if ($request->status === 'delivered') {
                $usedInventories = [];
    
                foreach ($inventoryItems as $invItem) {
                    if ($remainingQuantity <= 0) break;
    
                    $quantityToTake = min($remainingQuantity, $invItem->quantity);
    
                    // Deduct from warehouse InventoryItem
                    $invItem->decrement('quantity', $quantityToTake);
    
                    // Add to FacilityInventory & FacilityInventoryItem
                    $facilityInventory = FacilityInventory::firstOrCreate([
                        'facility_id' => $item->order->facility_id,
                        'product_id' => $item->product_id,
                    ]);
    
                    FacilityInventoryItem::create([
                        'inventory_id' => $facilityInventory->id,
                        'product_id' => $item->product_id,
                        'quantity' => $quantityToTake,
                        'batch_number' => $invItem->batch_number,
                        'expiry_date' => $invItem->expiry_date,
                        'warehouse_id' => $request->warehouse_id,
                    ]);
    
                    $usedInventories[] = [
                        'batch_number' => $invItem->batch_number,
                        'expiry_date' => $invItem->expiry_date->format('Y-m-d'),
                        'quantity' => $quantityToTake
                    ];
    
                    $remainingQuantity -= $quantityToTake;
                }
    
                // Clean up empty InventoryItems
                InventoryItem::where('product_id', $item->product_id)
                    ->where('warehouse_id', $request->warehouse_id)
                    ->where('quantity', '=', 0)
                    ->delete();
    
                $item->delivered = true;
                $item->delivered_at = now();
            }
    
            $item->status = $request->status;
            $item->save();
    
            // Check if all items in the order are delivered
            if ($item->order->items()->where('status', '!=', 'delivered')->count() === 0) {
                $item->order->status = 'completed';
                $item->order->save();
            }
    
            event(new OrderEvent('Refreshed'));
            Kafka::publishOrderPlaced('Refreshed');
    
            DB::commit();
            return response()->json('Order item status updated successfully.', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order item status change failed', [
                'error' => $e->getMessage(),
                'item_id' => $request->item_id ?? null
            ]);
            return response()->json('Failed to update order item status: ' . $e->getMessage(), 500);
        }
    }
    

    public function show(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('order-view') && !auth()->user()->hasPermission('order-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the orders module.');
        }
        try {
            DB::beginTransaction();
            $order = Order::where('orders.id', $id)
                ->with(['items.product.category','dispatch.driver','dispatch.logistic_company', 'items.inventory_allocations.warehouse', 'items.inventory_allocations.location','items.inventory_allocations.back_order', 'facility', 'user','reviewedBy', 'approvedBy', 'processedBy','dispatchedBy','deliveredBy','receivedBy','rejectedBy'])
                ->first();

            // Get items with SOH using subquery
            $items = DB::table('order_items')
                ->select([
                    'order_items.*',
                    'products.name as product_name',
                    'inventory_sums.total_quantity as soh'
                ])
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->leftJoin(DB::raw('(
                    SELECT product_id, SUM(quantity) as total_quantity
                    FROM inventories
                    GROUP BY product_id
                ) as inventory_sums'), 'products.id', '=', 'inventory_sums.product_id')
                ->where('order_items.order_id', $id)
                ->get();

            $order->items = $items;
            $products = Product::select('id','name')->get();
            
            // Get drivers with their companies
            $drivers = Driver::with('company')->where('is_active', true)->get();
            
            // Get all companies for the driver form
            $companyOptions = LogisticCompany::where('is_active', true)
                ->get()
                ->map(function($company) {
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'isAddNew' => false
                    ];
                })
                ->push([
                    'id' => 'new',
                    'name' => 'Add New Company',
                    'isAddNew' => true
                ]);
            
            DB::commit();
            return inertia("Order/Show", [
                'order' => $order, 
                'products' => $products,
                'drivers' => $drivers,
                'companyOptions' => $companyOptions
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return inertia("Order/Show", ['error' =>  $th->getMessage()]);
        }
    }

    public function pending(Request $request)
    {
        $query = Order::with(['facility', 'user'])
            ->where('status', 'pending');

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('facility', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $orders = $query->latest()->paginate(10);

        return Inertia::render('Order/Pending', [
            'orders' => OrderResource::collection($orders),
            'filters' => $request->only('search', 'page')
        ]);
    }

    public function approved(Request $request)
    {
        $orders = Order::with(['facility', 'user'])
            ->where('status', 'approved')
            ->latest()
            ->get();

        return Inertia::render('Order/Approved', [
            'orders' => $orders,
        ]);
    }

    public function inProcess(Request $request)
    {
        $orders = Order::with(['facility', 'user'])
            ->where('status', 'in_process')
            ->latest()
            ->get();

        return Inertia::render('Order/InProcess', [
            'orders' => OrderResource::collection($orders),
            'filters' => $request->only('search', 'page')
        ]);
    }

    public function dispatched(Request $request)
    {
        $orders = Order::with(['facility', 'user'])
            ->where('status', 'dispatched')
            ->latest()
            ->get();

        return Inertia::render('Order/Dispatched', [
            'orders' => OrderResource::collection($orders),
            'filters' => $request->only('search', 'page')
        ]);
    }

    public function delivered(Request $request)
    {
        $orders = Order::with(['facility', 'user'])
            ->where('status', 'delivered')
            ->latest()
            ->get();

        return Inertia::render('Order/Delivered', [
            'orders' => OrderResource::collection($orders),
            'filters' => $request->only('search', 'page')
        ]);
    }

    public function received(Request $request)
    {
        $orders = Order::with(['facility', 'user'])
            ->where('status', 'received')
            ->latest()
            ->get();

        return Inertia::render('Order/Received', [
            'orders' => OrderResource::collection($orders),
            'filters' => $request->only('search', 'page')
        ]);
    }

    public function all(Request $request)
    {
        $query = Order::with(['facility', 'user']);

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('facility', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $orders = $query->latest()->paginate(10);

        return Inertia::render('Order/All', [
            'orders' => OrderResource::collection($orders),
            'filters' => $request->only('search', 'page')
        ]);
    }
    
    public function restoreOrder(Request $request)
    {
        if (!auth()->user()->hasPermission('order-edit') && !auth()->user()->hasPermission('order-manage') && !auth()->user()->isAdmin()) {
            return response()->json('You do not have permission to restore orders', 403);
        }
        try {
            $order = Order::find($request->order_id);
            $order->status = 'pending';
            $order->rejected_at = null;
            $order->rejected_by = null;
            $order->reviewed_at = null;
            $order->reviewed_by = null;
            $order->save();
            return response()->json('Order restored successfully.', 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
}
