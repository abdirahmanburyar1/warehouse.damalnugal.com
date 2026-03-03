<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\BackOrderHistory;
use App\Models\Disposal;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Uom;
use App\Models\IssuedQuantity;
use App\Models\Location;
use App\Models\PackingList;
use App\Models\PackingListDifference;
use App\Http\Resources\PackingListResource;
use App\Models\PoDocument;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\BackOrderHistoryResource;
use App\Models\Facility;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\PackingListItem;
use App\Models\BackOrder;
use App\Models\ReceivedQuantity;
use App\Models\Liquidate;
use App\Models\LiquidateItem;
use App\Models\DisposalItem;
use App\Http\Resources\SupplierResource;
use App\Models\User;
use App\Notifications\PurchaseOrderActionRequired;
use App\Notifications\PackingListActionRequired;
use App\Notifications\ReceivedBackorderActionRequired;
use App\Notifications\DisposalActionRequired;
use App\Notifications\LiquidationActionRequired;
use Inertia\Inertia;

class SupplyController extends Controller
{
    /**
     * Display a listing of the supplies.
     */
    public function index(Request $request)
    {
        $purchaseOrders = PurchaseOrder::with('supplier')
            ->withSum('items', 'total_cost')
            ->when($request->filled('search'), function($query) use ($request) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('po_number', 'like', "%{$search}%")
                      ->orWhereHas('supplier', function($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->when($request->filled('supplier'), function($query) use ($request) {
                $query->whereHas('supplier', function($query) use ($request) {
                    $query->where('name', 'like', "%{$request->supplier}%");
                });
            })
            ->when($request->filled('status'), function($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderBy('po_number', 'desc')
            ->orderBy('created_at', 'desc');

        // Calculate lead times as difference between packing list confirm_at and PO po_date
        $leadTimes = DB::table('packing_lists as pl')
            ->join('purchase_orders as po', 'po.id', '=', 'pl.purchase_order_id')
            ->select(
                DB::raw('MAX(TIMESTAMPDIFF(MONTH, po.po_date, pl.confirmed_at)) as max_lead_time'),
                DB::raw('ROUND(AVG(TIMESTAMPDIFF(MONTH, po.po_date, pl.confirmed_at)), 1) as avg_lead_time'),
                DB::raw('MIN(TIMESTAMPDIFF(MONTH, po.po_date, pl.confirmed_at)) as low_lead_time'),
                DB::raw('COUNT(*) as total_pls')
            )
            ->where('pl.status', '=', 'approved')
            ->first();

        // Get statistics for the cards (all purchase orders)
        $stats = [
            'total_items' => PurchaseOrder::count(),
            'total_cost' => PurchaseOrder::join('purchase_order_items', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
                ->sum('purchase_order_items.total_cost'),
            'lead_times' => [
                'max' => round($leadTimes->max_lead_time ?? 0, 1) . ' Months',
                'avg' => round($leadTimes->avg_lead_time ?? 0, 1) . ' Months',
                'low' => round($leadTimes->low_lead_time ?? 0, 1) . ' Months',
            ],
            'pending_orders' => PurchaseOrder::where('status', 'pending')->count(),
            'back_orders' => PackingListDifference::count(), // Count the number of back orders instead of summing quantities
        ];

        $purchaseOrders = $purchaseOrders->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();

        $purchaseOrders->setPath(url()->current()); // Force Laravel to use full URLs

        return Inertia::render('Supplies/Index', [
            'purchaseOrders' => PurchaseOrderResource::collection($purchaseOrders),
            'filters' => $request->only('search', 'page','per_page', 'supplier','status'),
            'suppliers' => Supplier::pluck('name')->toArray(),
            'stats' => $stats
        ]);
    }

    public function create(Request $request){
        return inertia('Supplies/Create');
    }

    public function showPO(Request $request, $id){
        $po = PurchaseOrder::with('items.product','supplier','documents.uploader','creator','approvedBy','rejectedBy','reviewedBy')->find($id);
        return inertia("Supplies/PurchaseO/Show", [
            'po' => $po
        ]);
    }

    // showPackingList
    public function showPackingList(Request $request, $id){
        if (!auth()->user()->hasPermission('packing-list-view')) {
            abort(403, 'Unauthorized: You do not have permission to view packing lists.');
        }
        $packingList = PackingList::with('purchaseOrder.supplier','items.product.category','items.product.dosage','documents.uploader','confirmedBy','approvedBy','rejectedBy','reviewedBy','backOrder')
        ->where('id', $id)
        ->first();
        return inertia("Supplies/ShowPK", [
            'packing_list' => $packingList
        ]);
    }

    public function newPackingList(Request $request){
        if (!auth()->user()->hasPermission('packing-list-create')) {
            abort(403, 'Unauthorized: You do not have permission to create packing lists.');
        }
        $purchaseOrders = PurchaseOrder::where('status', 'approved')
            ->whereDoesntHave('packingLists')
            ->select('id','po_number','supplier_id','po_date','po_number','status')
            ->with(['supplier'])
            ->latest()
            ->get();
        $warehouses = Warehouse::where('id', auth()->user()->warehouse_id)->select('id', 'name')->get();
        return inertia("Supplies/PackingList", [
            'purchaseOrders' => $purchaseOrders,
            'warehouses' => $warehouses,
            'locations' => Location::get(),
        ]);
    }

    public function getBackOrder(Request $request, $id)
    {
        try {
            // Check if packing list exists
            $packingList = PackingList::find($id);
            if (!$packingList) {
                return response()->json(['error' => 'Packing list not found'], 404);
            }

            // Get packing list differences with related data (including processed/finalized so packing list column is always available)
            $results = PackingListDifference::whereHas('packingListItem', function($query) use ($id) {
                $query->where('packing_list_id', $id);
            })
                ->select('id', 'packing_list_item_id', 'back_order_id', 'product_id', 'quantity', 'original_quantity', 'status', 'finalized', 'created_at')
                ->with([
                    'product:id,name,productID',
                    'packingListItem.packingList:id,packing_list_number',
                    'backOrder:id,back_order_number,back_order_date,status'
                ])
                ->get();

            // Ensure each item has packing_list_number at top level so the Back Order table column is never empty (handles any serialization shape)
            $results = $results->map(function ($row) {
                $arr = $row->toArray();
                $pl = $row->packingListItem?->packingList;
                $arr['packing_list_number'] = $pl?->packing_list_number;
                $arr['packing_list'] = $pl ? ['id' => $pl->id, 'packing_list_number' => $pl->packing_list_number] : null;
                return $arr;
            });

            return response()->json($results, 200);

        } catch (\Throwable $th) {
            logger()->error('Error in getBackOrder: ' . $th->getMessage(), [
                'packing_list_id' => $id,
                'trace' => $th->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to fetch back order data'], 500);
        }
    }
    
    public function deleteDocument(Request $request, $id)
    {
        try {
            $document = PoDocument::findOrFail($id);
            
            // Delete the physical file
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }
            
            // Delete the database record
            $document->delete();
            
            return response()->json(['message' => 'Document deleted successfully']);
            
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error deleting document: ' . $e->getMessage()], 500);
        }
    }

    public function liquidate(Request $request)
    {
        try {
            DB::beginTransaction();
            
            // Get the back order to determine the source
            $backOrder = BackOrder::findOrFail($request->back_order_id);
            
            // Check if there is already a liquidation for this back order
            $liquidate = Liquidate::where('back_order_id', $request->back_order_id)->first();
            
            $liquidateIsNew = false;
            if (!$liquidate) {
                // Create new liquidation record
                $liquidate = Liquidate::create([
                    'liquidated_by' => auth()->id(),
                    'liquidated_at' => now(),
                    'status' => 'pending',
                    'source' => $backOrder->source_type, // Get source from BackOrder
                    'back_order_id' => $request->back_order_id,
                    'packing_list_id' => $request->packing_list_id,
                    'order_id' => $request->purchase_order_id, // or $request->order_id if that's the field
                    'transfer_id' => $request->transfer_id,
                ]);
                $liquidateIsNew = true;
            }
            
            // Get packing list item to get unit cost
            $packingListItem = PackingListItem::with(['product', 'purchaseOrderItem'])->find($request->packing_list_item_id);
            
            // Try to get unit cost from multiple sources
            $unitCost = 0;
            if ($packingListItem) {
                // First try packing list item unit cost
                if ($packingListItem->unit_cost && $packingListItem->unit_cost > 0) {
                    $unitCost = $packingListItem->unit_cost;
                }
                // If not available, try purchase order item cost
                elseif ($packingListItem->purchaseOrderItem && $packingListItem->purchaseOrderItem->unit_cost) {
                    $unitCost = $packingListItem->purchaseOrderItem->unit_cost;
                }
                // If still not available, use a default value of 1.00
                else {
                    $unitCost = 1.00;
                }
            }
            
            $totalCost = $unitCost * $request->quantity;
            
            // Get current user's warehouse
            $user = auth()->user()->load('warehouse');
            $warehouseName = $user->warehouse ? $user->warehouse->name : null;
            
            // Create liquidation item
            $liquidateItem = LiquidateItem::create([
                'liquidate_id' => $liquidate->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'barcode' => $packingListItem->barcode ?? null,
                'expire_date' => $packingListItem->expire_date ?? null,
                'batch_number' => $packingListItem->batch_number ?? null,
                'uom' => $packingListItem->uom ?? null,
                'location' => $packingListItem->location ?? null,
                'facility' => null, // Facility will be null
                'warehouse' => $warehouseName,
                'note' => $request->note,
                'type' => $request->type,
            ]);
            
            // Update the packing list difference to mark as finalized
            $packingListDiff = PackingListDifference::find($request->id);
            if ($packingListDiff) {
                $packingListDiff->update([
                    'finalized' => true
                ]);
                
                // Update inventory allocation if it exists
                if ($packingListDiff->inventory_allocation_id) {
                    $inventoryAllocation = \App\Models\InventoryAllocation::find($packingListDiff->inventory_allocation_id);
                    if ($inventoryAllocation) {
                        $inventoryAllocation->increment('received_quantity', $request->quantity);
                        $inventoryAllocation->save();
                    }
                }
            }

            // Notify users with liquidation-review permission when new liquidation is created (next action = review)
            if ($liquidateIsNew) {
                $reviewers = User::withPermission('liquidation-review')
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->where('id', '!=', auth()->id())
                    ->get();
                foreach ($reviewers as $user) {
                    $user->notify(new LiquidationActionRequired($liquidate, LiquidationActionRequired::ACTION_NEEDS_REVIEW));
                }
            }
            
            // Handle attachments if any
            if ($request->hasFile('attachments')) {
                // Validate attachments without size restrictions
                $request->validate([
                    'attachments.*' => 'file|mimes:pdf|nullable'
                ]);
                
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('attachments/liquidations', 'public');
                    $attachments[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType()
                    ];
                }
                
                // Store attachments in the liquidation item
                $liquidateItem->update(['attachments' => $attachments]);
            }
            
            DB::commit();
            
            return response()->json('Item liquidated successfully', 200);
            
        } catch (\Throwable $th) {
            DB::rollBack();
            logger()->error('Liquidation error: ' . $th->getMessage());
            return response()->json('Failed to liquidate item: ' . $th->getMessage(), 500);
        }
    }
    
    public function dispose(Request $request)
    {
        try {
            DB::beginTransaction();
            
            // Get the back order to determine the source
            $backOrder = BackOrder::findOrFail($request->back_order_id);
            
            // Check if there is already a disposal for this back order
            $disposal = Disposal::where('back_order_id', $request->back_order_id)->first();
            
            $disposalIsNew = false;
            if (!$disposal) {
                // Create new disposal record
                $disposal = Disposal::create([
                    'disposed_by' => auth()->id(),
                    'disposed_at' => now(),
                    'status' => 'pending',
                    'source' => $backOrder->source_type, // Get source from BackOrder
                    'back_order_id' => $request->back_order_id,
                ]);
                $disposalIsNew = true;
            }
            
            // Get packing list item to get unit cost
            $packingListItem = PackingListItem::with(['product', 'purchaseOrderItem'])->find($request->packing_list_item_id);
            
            // Try to get unit cost from multiple sources
            $unitCost = 0;
            if ($packingListItem) {
                // First try packing list item unit cost
                if ($packingListItem->unit_cost && $packingListItem->unit_cost > 0) {
                    $unitCost = $packingListItem->unit_cost;
                }
                // If not available, try purchase order item cost
                elseif ($packingListItem->purchaseOrderItem && $packingListItem->purchaseOrderItem->unit_cost) {
                    $unitCost = $packingListItem->purchaseOrderItem->unit_cost;
                }
                // If still not available, use a default value of 1.00
                else {
                    $unitCost = 1.00;
                }
            }
            
            $totalCost = $unitCost * $request->quantity;
            
            // Get current user's warehouse
            $user = auth()->user()->load('warehouse');
            $warehouseName = $user->warehouse ? $user->warehouse->name : null;
            
            // Create disposal item
            $disposalItem = DisposalItem::create([
                'disposal_id' => $disposal->id,
                'product_id' => $packingListItem->product_id,
                'quantity' => $request->quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'barcode' => $packingListItem->barcode ?? null,
                'expire_date' => $packingListItem->expire_date ?? null,
                'batch_number' => $packingListItem->batch_number ?? null,
                'uom' => $packingListItem->uom ?? null,
                'location' => $packingListItem->location ?? null,
                'facility' => null, // Facility will be null
                'warehouse' => $warehouseName,
                'note' => $request->note,
                'type' => $request->type,
            ]);
            
            // Update the packing list difference to mark as finalized
            $packingListDiff = PackingListDifference::find($request->id);
            if ($packingListDiff) {
                $packingListDiff->update([
                    'finalized' => true
                ]);
                
                // Update inventory allocation if it exists
                if ($packingListDiff->inventory_allocation_id) {
                    $inventoryAllocation = \App\Models\InventoryAllocation::find($packingListDiff->inventory_allocation_id);
                    if ($inventoryAllocation) {
                        $inventoryAllocation->increment('received_quantity', $request->quantity);
                        $inventoryAllocation->save();
                    }
                }
            }

            // Notify users with disposal-review permission when new disposal is created from back order (next action = review)
            if ($disposalIsNew) {
                $reviewers = User::withPermission('disposal-review')
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->where('id', '!=', auth()->id())
                    ->get();
                foreach ($reviewers as $user) {
                    $user->notify(new DisposalActionRequired($disposal, DisposalActionRequired::ACTION_NEEDS_REVIEW));
                }
            }
            
            // Handle attachments if any
            if ($request->hasFile('attachments')) {
                // Validate attachments without size restrictions
                $request->validate([
                    'attachments.*' => 'file|mimes:pdf|nullable'
                ]);
                
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('attachments/disposals', 'public');
                    $attachments[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType()
                    ];
                }
                
                // Store attachments in the disposal item
                $disposalItem->update(['attachments' => $attachments]);
            }
            
            DB::commit();
            
            return response()->json('Item disposed successfully', 200);
            
        } catch (\Throwable $th) {
            DB::rollBack();
            logger()->error('Disposal error: ' . $th->getMessage());
            return response()->json('Failed to dispose item: ' . $th->getMessage(), 500);
        }
    }
    
    public function receive(Request $request)
    {
        try {
            // Log the incoming request for debugging
            logger()->info('Receive request received:', [
                'all_request_data' => $request->all(),
                'id' => $request->id,
                'back_order_id' => $request->back_order_id,
                'product_id' => $request->product_id,
                'packing_list_item_id' => $request->packing_list_item_id
            ]);
            
            // Validate the request
            $validated = $request->validate([
                'id' => 'required|exists:packing_list_differences,id',
                'product_id' => 'required|exists:products,id',
                'packing_list_item_id' => 'required|exists:packing_list_items,id',
                'quantity' => 'required|integer|min:1',
                'original_quantity' => 'required|integer|min:1',
                'status' => 'nullable|string',
                'packing_list_id' => 'nullable|exists:packing_lists,id',
                'packing_list_number' => 'nullable|string',
                'purchase_order_id' => 'nullable',
                'total_cost' => 'nullable|numeric',
                'back_order_id' => 'required|exists:back_orders,id',
            ]);
            
            // Start a database transaction
            DB::beginTransaction();
            
            // Find the packing list difference record and back order
            $packingListDiff = PackingListDifference::with(['packingListItem', 'backOrder'])
                ->where('id', $request->id)
                ->first();
                
            // Log for debugging
            logger()->info('Receive request:', [
                'id' => $request->id,
                'status' => $request->status,
                'found_diff' => $packingListDiff ? $packingListDiff->status : 'not found',
                'found_diff_id' => $packingListDiff ? $packingListDiff->id : 'not found',
                'found_diff_finalized' => $packingListDiff ? $packingListDiff->finalized : 'not found'
            ]);
            
            // Also check if there are any records with this ID at all
            $allRecordsWithId = PackingListDifference::where('id', $request->id)->get();
            logger()->info('All records with ID ' . $request->id . ':', $allRecordsWithId->toArray());
            
            // Check all records for this packing_list_item_id to see if there are separate records for different statuses
            $allRecordsForPackingListItem = PackingListDifference::where('packing_list_item_id', $request->packing_list_item_id)->get();
            $packingListItem = PackingListItem::find($request->packing_list_item_id);
            $backOrder = BackOrder::with(['packingList', 'order', 'transfer'])->find($request->back_order_id);
            
            if (!$packingListDiff) {
                return response()->json([
                    'message' => 'Back order item not found',
                    'debug' => [
                        'requested_id' => $request->id,
                        'requested_status' => $request->status,
                        'back_order_id' => $request->back_order_id
                    ]
                ], 404);
            }
            
            if (!$backOrder) {
                return response()->json([
                    'message' => 'Back order not found'
                ], 404);
            }
            
            // Calculate the remaining quantity
            $receivedQuantity = $request->quantity;
            $originalQuantity = $request->original_quantity;
            $remainingQuantity = $originalQuantity - $receivedQuantity;
            
            // Create a record in BackOrderHistory for the received items
            $backOrderHistoryData = [
                'packing_list_id' => $backOrder->packing_list_id,
                'product_id' => $packingListItem->product_id,
                'quantity' => $receivedQuantity,
                'status' => 'Received',
                'note' => "Received from Back Order: {$request->packing_list_number}",
                'performed_by' => auth()->user()->id,
                'barcode' => $packingListItem->barcode,
                'batch_number' => $packingListItem->batch_number,
                'expiry_date' => $packingListItem->expire_date,
                'back_order_id' => $request->back_order_id,
                'uom' => $packingListItem->uom,
                'unit_cost' => $packingListItem->unit_cost,
                'total_cost' => $packingListItem->unit_cost * $receivedQuantity,
            ];

            // Set order_item_id or transfer_item_id based on back order type
            if ($backOrder->order_id) {
                // Find the order item for this product/order
                $orderItem = \App\Models\OrderItem::where('order_id', $backOrder->order_id)
                    ->where('product_id', $packingListItem->product_id)
                    ->first();
                if ($orderItem) {
                    $backOrderHistoryData['order_item_id'] = $orderItem->id;
                }
            } elseif ($backOrder->transfer_id) {
                // Find the transfer item for this product/transfer
                $transferItem = \App\Models\TransferItem::where('transfer_id', $backOrder->transfer_id)
                    ->where('product_id', $packingListItem->product_id)
                    ->first();
                if ($transferItem) {
                    $backOrderHistoryData['transfer_item_id'] = $transferItem->id;
                }
            }

            $backOrderHistory = BackOrderHistory::create($backOrderHistoryData);
            
            // Determine the back order type and set appropriate relationships
            $backOrderType = 'Packing List'; // Default
            $orderId = null;
            $transferId = null;
            $facilityId = null;
            
            if ($backOrder->packing_list_id) {
                $backOrderType = 'Packing List';
                // Packing list back order - already has packing_list_id
            } elseif ($backOrder->order_id) {
                $backOrderType = 'order';
                $orderId = $backOrder->order_id;
                // Get facility_id from the order if available
                if ($backOrder->order && $backOrder->order->facility_id) {
                    $facilityId = $backOrder->order->facility_id;
                }
            } elseif ($backOrder->transfer_id) {
                $backOrderType = 'transfer';
                $transferId = $backOrder->transfer_id;
                // Get facility_id from the transfer if available
                if ($backOrder->transfer && $backOrder->transfer->to_facility_id) {
                    $facilityId = $backOrder->transfer->to_facility_id;
                }
            }
            
            // Create a received back order record (pending approval)
            $receivedBackorder = \App\Models\ReceivedBackorder::create([
                'received_by' => auth()->user()->id,
                'received_at' => now(),
                'status' => 'pending',
                'type' => $backOrderType,
                'warehouse_id' => $packingListItem->warehouse_id ?? null,
                'facility_id' => $facilityId,
                'back_order_id' => $request->back_order_id,
                'packing_list_id' => $backOrder->packing_list_id,
                'order_id' => $orderId,
                'transfer_id' => $transferId,
                'packing_list_number' => $request->packing_list_number,
                'purchase_order_id' => $request->purchase_order_id,
            ]);
            
            // Create the received backorder item
            $receivedBackorder->items()->create([
                'product_id' => $request->product_id,
                'quantity' => $receivedQuantity,
                'unit_cost' => $packingListItem->unit_cost ?? 0,
                'total_cost' => ($packingListItem->unit_cost ?? 0) * $receivedQuantity,
                'barcode' => $packingListItem->barcode ?? null,
                'batch_number' => $packingListItem->batch_number ?? null,
                'expire_date' => $packingListItem->expire_date ?? null,
                'uom' => $packingListItem->uom ?? null,
                'warehouse_id' => $packingListItem->warehouse_id ?? null,
                'location' => $packingListItem->location ?? null,
                'note' => "Received from Back Order: {$request->packing_list_number}",
            ]);

            // Notify users with received-backorder review permission (next action = review)
            $reviewers = User::withPermission('received-backorder-review')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            foreach ($reviewers as $user) {
                $user->notify(new ReceivedBackorderActionRequired($receivedBackorder, ReceivedBackorderActionRequired::ACTION_NEEDS_REVIEW));
            }
            
            // Update inventory allocation if it exists
            if ($packingListDiff->inventory_allocation_id) {
                $inventoryAllocation = \App\Models\InventoryAllocation::find($packingListDiff->inventory_allocation_id);
                if ($inventoryAllocation) {
                    $inventoryAllocation->increment('received_quantity', $receivedQuantity);
                    $inventoryAllocation->save();
                }
            }
            
            // Decrement the packing list difference quantity
            $packingListDiff->decrement('quantity', $receivedQuantity);

            // When fully received (remaining = 0), mark as finalized and store original quantity for display
            if ($packingListDiff->quantity <= 0) {
                $packingListDiff->update([
                    'finalized' => true,
                    'original_quantity' => $originalQuantity,
                ]);
            }
            
            // Note: Inventory is not updated directly during receive action
            // The received quantities are stored in ReceivedBackorder and BackOrderHistory
            // Inventory will be updated only when the received backorder is approved
            
            // Commit the transaction
            DB::commit();
            
            return response()->json([
                'message' => "Successfully received {$receivedQuantity} items from back order",
                'received_quantity' => $receivedQuantity,
                'remaining_quantity' => $packingListDiff->quantity
            ], 200);
            
        } catch (\Throwable $th) {
            // Rollback the transaction in case of error
            DB::rollBack();
            
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function backOrder(Request $request){
        $packingList = PackingList::whereIn('status', ['pending', 'approved'])
            ->whereHas('items.differences')
            ->select('id', 'packing_list_number')
            ->with('purchaseOrder:id,po_number')
            ->orderBy('created_at', 'desc')
            ->get();
        return inertia("Supplies/BackOrder", [
            'packingList' => $packingList
        ]);
    }

    public function getPO(Request $request, $id)
    {
        try {
            // Fetch the purchase order with supplier
            $po = PurchaseOrder::with('supplier')->findOrFail($id);
    
            // Get the latest packing list for this PO
            $latestPackingList = PackingList::where('purchase_order_id', $id)
                ->latest('created_at')
                ->first();
    
            // Fetch purchase order items with related products and packing list items using joins
            $items = DB::table('purchase_order_items as poi')
                ->select(
                    'poi.id',
                    'poi.purchase_order_id',
                    'poi.product_id',
                    'poi.quantity',
                    'poi.unit_cost as po_unit_cost',
                    'poi.total_cost as po_total_cost',
                    'poi.uom as po_uom',
                    'p.name as product_name',
                    'pli.barcode',
                    'pl.id as packing_list_id',
                    'pli.id as packing_list_item_id',
                    'pli.location', 
                    'pli.quantity as received_quantity',
                    'pli.batch_number',
                    'pli.expire_date',
                    'pl.status as pl_status',
                    'w.name as warehouse_name',
                    'w.id as warehouse_id',
                    'pli.unit_cost',
                    'pli.total_cost',
                    'pl.created_at as pl_created_at'
                )
                ->join('products as p', 'p.id', '=', 'poi.product_id')
                ->leftJoin('packing_lists as pl', function ($join) use ($id) {
                    $join->on('pl.purchase_order_id', '=', DB::raw($id));
                })
                ->leftJoin('packing_list_items as pli', function ($join) {
                    $join->on('pli.po_item_id', '=', 'poi.id')
                         ->on('pli.packing_list_id', '=', 'pl.id');
                })
                ->leftJoin('warehouses as w', 'w.id', '=', 'pli.warehouse_id')
                ->where('poi.purchase_order_id', $id)
                ->get();
    
            // Group by purchase order item id to aggregate packing list data
            $groupedItems = $items->groupBy('id');
    
            $transformedItems = $groupedItems->map(function ($group) {
                $first = $group->first();
    
                $totalReceivedQty = $group->sum('received_quantity') ?? 0;
                $remainingQty = $first->quantity - $totalReceivedQty;
    
                // Determine latest packing list status based on created_at timestamp
                $latestPLItem = $group->filter(fn($item) => $item->pl_status !== null)
                                      ->sortByDesc('pl_created_at')
                                      ->first();
    
                $status = $latestPLItem->pl_status ?? 'pending';
    
                // Build packing list details array
                $packingLists = $group->filter(fn($item) => $item->packing_list_id !== null)
                    ->map(function ($plItem) {
                        return [
                            'id' => $plItem->packing_list_id,
                            'quantity' => $plItem->received_quantity,
                            'batch_number' => $plItem->batch_number,
                            'barcode' => $plItem->barcode,
                            'expire_date' => $plItem->expire_date,
                            'warehouse_id' => $plItem->warehouse_id,
                            'warehouse' => $plItem->warehouse_id ? [
                                'id' => $plItem->warehouse_id,
                                'name' => $plItem->warehouse_name,
                            ] : null,
                            'location' => $plItem->location,
                            'status' => $plItem->pl_status,
                            'uom' => $plItem->po_uom,
                            'differences' => [] // to be filled later
                        ];
                    })->values();
    
                // Fetch all differences for packing lists of this PO item in a single query
                $plIds = $packingLists->pluck('id')->all();
                $differences = DB::table('packing_list_differences')
                    ->whereIn('packing_list_id', $plIds)
                    ->get()
                    ->map(function ($diff) {
                        return [
                            'id' => $diff->id,
                            'quantity' => $diff->quantity,
                            'status' => $diff->status,
                            'created_at' => $diff->created_at,
                        ];
                    })
                    ->groupBy('packing_list_id');
    
                // Attach differences to packing lists
                $packingLists = $packingLists->map(function ($pl) use ($differences) {
                    $pl['differences'] = $differences[$pl['id']] ?? [];
                    return $pl;
                });
    
                // Use latest packing list for warehouse and location info
                $latestPL = $group->filter(fn($item) => $item->packing_list_id !== null)
                                  ->sortByDesc('pl_created_at')
                                  ->first();
    
                return [
                    'id' => $latestPL->packing_list_id ?? null,
                    'product_id' => $first->product_id,
                    'po_item_id' => $first->id,
                    'quantity' => $first->quantity,
                    'unit_cost' => $first->po_unit_cost,
                    'total_cost' => $remainingQty * $first->po_unit_cost,
                    'searchQuery' => $first->product_name,
                    'barcode' => $first->barcode,
                    'warehouse_id' => $latestPL->warehouse_id ?? null,
                    'expire_date' => $latestPL->expire_date ?? null,
                    'location' => $latestPL->location ?? null,
                    'status' => $status,
                    'uom' => $first->po_uom,
                    'batch_number' => $latestPL->batch_number ?? null,
                    'fullfillment_rate' => $first->quantity > 0
                        ? round(($totalReceivedQty / $first->quantity) * 100, 2) . '%'
                        : '0%',
                    'received_quantity' => $totalReceivedQty,
                    'mismatches' => $remainingQty,
                    'product' => [
                        'id' => $first->product_id,
                        'name' => $first->product_name,
                    ],
                    'warehouse' => $latestPL ? [
                        'id' => $latestPL->warehouse_id,
                        'name' => $latestPL->warehouse_name,
                    ] : null,
                    'packing_lists' => $packingLists,
                ];
            })->values();
    
            // Prepare response
            $result = $po->toArray();
            $result['items'] = $transformedItems;
    
            if ($latestPackingList) {
                $result['packing_list_number'] = $latestPackingList->packing_list_number;
                $result['ref_no'] = $latestPackingList->ref_no;
                $result['pk_date'] = $latestPackingList->pk_date;
                $result['status'] = $latestPackingList->status;
            } else {
                $result['packing_list_number'] = sprintf("PKL-%s-001", substr($po->po_number, 3));
                $result['status'] = 'pending';
                $result['ref_no'] = "";
                $result['pk_date'] = "";
            }
    
            return response()->json($result, 200);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    
    public function storePK(Request $request)
    {
        if (!auth()->user()->hasPermission('packing-list-create')) {
            return response()->json('Unauthorized: You do not have permission to create packing lists', 403);
        }
        try {
            return DB::transaction(function() use ($request){
                $validated = $request->validate([
                    'id' => 'nullable',
                    'id' => 'required|exists:purchase_orders,id',
                    'packing_list_number' => 'required',
                    'ref_no' => 'nullable',
                    'pk_date' => 'required|date',
                    'items' => 'required|array',
                    'items.*.product_id' => 'required|exists:products,id',
                    'items.*.warehouse_id' => 'required|exists:warehouses,id',
                    'items.*.received_quantity' => 'required|numeric|min:0',
                    'items.*.po_item_id' => 'required|exists:purchase_order_items,id',
                    'items.*.id' => 'nullable',
                    'items.*.quantity' => 'required|numeric',
                    'items.*.barcode' => 'nullable|string',
                    'items.*.uom' => 'required|string',
                    'items.*.batch_number' => 'required|string',
                    'items.*.expire_date' => 'required|date',
                    'items.*.location' => 'required|string',
                    'items.*.unit_cost' => 'required|numeric|min:0',
                    'items.*.total_cost' => 'required|numeric|min:0',
                    'items.*.status' => 'required|in:pending,approved,rejected',
                    'items.*.differences' => 'nullable|array',
                    'items.*.differences.*.id' => 'nullable',
                    'items.*.differences.*.quantity' => 'required_with:items.*.differences|numeric|min:0',
                    'items.*.differences.*.status' => 'required_with:items.*.differences|in:Missing,Damaged,Lost,Expired,Low quality',
                ]);

                $request['purchase_order_id'] = $request->id;
                
                // Create or update the packing list
                $packingList = PackingList::updateOrCreate(
                    ['id' => $request->id],
                    [
                        'packing_list_number' => $request->packing_list_number,
                        'purchase_order_id' => $request->purchase_order_id,
                        'ref_no' => $request->ref_no,
                        'pk_date' => $request->pk_date,
                        'status' => 'pending',
                        'confirmed_by' => auth()->id(),
                        'confirmed_at' => now(),
                    ]
                );

                // Check if any items have differences to determine if we need a BackOrder
                $hasBackOrderItems = collect($request->items)
                    ->filter(function($item) {
                        return !empty($item['differences']);
                    })
                    ->isNotEmpty();

                $backOrder = null;
                if ($hasBackOrderItems) {
                    // Create or update parent BackOrder
                    $backOrder = BackOrder::firstOrCreate(
                        ['packing_list_id' => $packingList->id],
                        [
                            'back_order_date' => now()->toDateString(),
                            'created_by' => auth()->id(),
                            'status' => 'pending',
                            'source_type' => 'packing_list',
                            'reported_by' => auth()->user()->load('warehouse')->warehouse->name ?? 'Unknown Warehouse'
                        ]
                    );
                }

                // Process each item
                foreach($request->items as $item) {
                    // Create or update packing list item
                    $packingListItem = PackingListItem::updateOrCreate(
                        [
                            'id' => $item['id'] ?? null,
                            'packing_list_id' => $packingList->id,
                            'po_item_id' => $item['po_item_id']
                        ],
                        [
                            'product_id' => $item['product_id'],
                            'warehouse_id' => $item['warehouse_id'],
                            'location' => $item['location'],
                            'barcode' => $item['barcode'],
                            'batch_number' => $item['batch_number'],
                            'quantity' => $item['received_quantity'],
                            'uom' => $item['uom'],
                            'expire_date' => $item['expire_date'],
                            'unit_cost' => $item['unit_cost'],
                            'total_cost' => $item['unit_cost'] * $item['received_quantity']
                        ]
                    );

                    // Handle differences
                    if (!empty($item['differences'])) {
                        foreach ($item['differences'] as $diff) {
                            $packingListItem->differences()->updateOrCreate(
                                ['id' => $diff['id'] ?? null],
                                [
                                    'back_order_id' => $backOrder->id,
                                    'product_id' => $item['product_id'],
                                    'quantity' => $diff['quantity'],
                                    'status' => $diff['status'],
                                    'notes' => $diff['notes'] ?? null,
                                ]
                            );
                        }
                    } else {
                        // Delete any existing differences if the array is empty
                        $packingListItem->differences()
                            ->where('product_id', $item['product_id'])
                            ->delete();
                    }
                }

                // Update BackOrder totals if it exists
                if ($backOrder) {
                    $backOrder->updateTotals();
                }

                // Notify users with "review" permission when a new packing list is created (next action = review)
                if ($packingList->wasRecentlyCreated) {
                    $reviewers = User::withPermission('packing-list-review')
                        ->where('is_active', true)
                        ->whereNotNull('email')
                        ->where('id', '!=', auth()->id())
                        ->get();
                    foreach ($reviewers as $user) {
                        $user->notify(new PackingListActionRequired($packingList, PackingListActionRequired::ACTION_NEEDS_REVIEW));
                    }
                }

                return response()->json('Packing list created successfully', 200);
            });
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }        

    public function newPO()
    {
        $products = Product::select('id','name')->get();
        $suppliers = Supplier::get();
        $uom = Uom::pluck('name')->toArray();
        
        // Get the last PO number and increment it
        $lastPO = PurchaseOrder::latest()->first();
        $nextPONumber = $lastPO ? 'PO-' . str_pad((intval(substr($lastPO->po_number, 3)) + 1), 6, '0', STR_PAD_LEFT) : 'PO-000001';

        return inertia('Supplies/NewPo', [
            'products' => $products,
            'suppliers' => $suppliers,
            'po_number' => $nextPONumber,
            'uom' => $uom
        ]);
    }

    public function editPO($id)
    {
        $po = PurchaseOrder::with([
            'items.product',
            'supplier',
            'reviewedBy:id,name',
            'approvedBy:id,name',
            'rejectedBy:id,name'
        ])->findOrFail($id);

        if (in_array(strtolower($po->status ?? ''), ['approved', 'completed'])) {
            return redirect()->route('supplies.index')
                ->with('error', 'Approved or completed purchase orders cannot be edited.');
        }

        return inertia('Supplies/EditPo', [
            'po' => $po,
            'products' => Product::select('id', 'name', 'productID')->get(),
            'uom' => Uom::pluck('name')->toArray(),
            'suppliers' => Supplier::select('id', 'name', 'contact_person', 'email', 'phone', 'address')->get(),
        ]);
    }

    public function storePO(Request $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $validated = $request->validate([
                    'id' => 'nullable|integer',
                    'supplier_id' => 'required|exists:suppliers,id',
                    'po_number' => 'required|unique:purchase_orders,po_number,' . $request->id,
                    'po_date' => 'required|date',
                    'expected_date' => 'required|date',
                    'original_po_no' => 'nullable',
                    'notes' => 'nullable',
                    'items' => 'required|array|min:1',
                    'items.*.id' => 'nullable|integer',
                    'items.*.product_id' => 'required|exists:products,id',
                    'items.*.unit_cost' => 'required|numeric|min:0',
                    'items.*.uom' => 'nullable',
                    'items.*.quantity' => 'required|integer|min:1',
                    'items.*.total_cost' => 'required|numeric|min:0'
                ]);
                
                $po = PurchaseOrder::updateOrCreate([
                    'id' => $request->id
                ], [
                    'po_number' => $validated['po_number'],
                    'notes' => $validated['notes'],
                    'original_po_no' => $validated['original_po_no'],
                    'po_date' => $validated['po_date'],
                    'expected_date' => $validated['expected_date'] ?? null,
                    'supplier_id' => $validated['supplier_id'],
                    'created_by' => auth()->id()
                ]);


                // Process each item individually
                foreach ($validated['items'] as $item) {
                    // Prepare the data for update or create
                    if($item['product_id'] == null) continue;
                    $itemData = [
                        'purchase_order_id' => $po->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'uom' => $item['uom'],
                        'unit_cost' => $item['unit_cost'],
                        'total_cost' => $item['total_cost'],
                        'updated_at' => now() // Force update by changing timestamp
                    ];
                    
                    // Track original values when editing an existing item
                    if (isset($item['id'])) {
                        $existingItem = PurchaseOrderItem::find($item['id']);
                        
                        // Check if quantity has changed
                        logger()->info('Quantity comparison - Current: ' . $existingItem->quantity . ', New: ' . $item['quantity'] . ', Original: ' . $existingItem->original_quantity);
                        
                        // Check if this is a rollback to original value
                        if ($existingItem->original_quantity !== null && (int)$item['quantity'] == (int)$existingItem->original_quantity) {
                            // Rollback detected - clear original_quantity
                            $itemData['original_quantity'] = null;
                            $itemData['edited_by'] = auth()->id();
                            logger()->info('Rolling back quantity to original, clearing original_quantity');
                        } elseif ($existingItem->quantity != $item['quantity']) {
                            // Quantity changed - capture original if not already set
                            if ($existingItem->original_quantity === null) {
                                $itemData['original_quantity'] = $existingItem->quantity;
                                logger()->info('Capturing original quantity: ' . $existingItem->quantity);
                            }
                            $itemData['edited_by'] = auth()->id();
                        } else {
                            logger()->info('No quantity change detected');
                        }
                        
                        // Check if UOM has changed
                        if ($existingItem->uom != $item['uom']) {
                            // If original_uom is not set, capture it (first change)
                            if ($existingItem->original_uom === null) {
                                $itemData['original_uom'] = $existingItem->uom;
                                logger()->info('Capturing original UOM: ' . $existingItem->uom);
                            }
                            $itemData['edited_by'] = auth()->id();
                        } else {
                            // If UOM is rolled back to original, clear original_uom
                            if ($existingItem->original_uom !== null && $item['uom'] == $existingItem->original_uom) {
                                $itemData['original_uom'] = null;
                                $itemData['edited_by'] = auth()->id();
                                logger()->info('Rolling back UOM to original, clearing original_uom');
                            }
                        }
                        
                        logger()->info('PO Item Changes (storePO)', [
                            'item_id' => $existingItem->id,
                            'old_quantity' => $existingItem->quantity,
                            'new_quantity' => $item['quantity'],
                            'old_uom' => $existingItem->uom,
                            'new_uom' => $item['uom'],
                            'original_quantity' => $itemData['original_quantity'] ?? null,
                            'original_uom' => $itemData['original_uom'] ?? null,
                            'edited_by' => $itemData['edited_by'] ?? null
                        ]);
                    }
                    
                    // Update or create the purchase order item
                    if (isset($item['id'])) {
                        // Force update even if data hasn't changed to ensure original tracking is processed
                        $existingItem = PurchaseOrderItem::find($item['id']);
                        if ($existingItem) {
                            $existingItem->update($itemData);
                            $poItem = $existingItem;
                        } else {
                            $poItem = PurchaseOrderItem::create($itemData);
                        }
                    } else {
                        $poItem = PurchaseOrderItem::create($itemData);
                    }
                }

                // Notify users with "review" permission when a new PO is created (next action = review)
                if (!$request->id) {
                    $po->load('supplier');
                    $reviewers = User::withPermission('purchase-order-review')
                        ->where('is_active', true)
                        ->whereNotNull('email')
                        ->where('id', '!=', auth()->id())
                        ->get();
                    foreach ($reviewers as $user) {
                        $user->notify(new PurchaseOrderActionRequired($po, PurchaseOrderActionRequired::ACTION_NEEDS_REVIEW));
                    }
                }
                
                return response()->json($request->id ? 'Purchase order updated successfully' : 'Purchase order created successfully', 200);
            });
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function searchsupplier(Request $request, $search){
        try {
            $supplier = Supplier::where('name', 'like', "%{$search}%")->first();
            return response()->json($supplier, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    
    public function getSupplier(Request $request, $id){
        try {
            $supplier = Supplier::find($id);
            $supplier['po_number'] = 98887;
            return response()->json($supplier, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function searchProduct(Request $request, $id){
        try {
            $product = Product::find($id);
            return response()->json($product, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Display the specified supply.
     */
    public function reviewPO($id)
    {
        try {
            // Check permission
            if (!auth()->user()->hasPermission('purchase-order-review')) {
                return response()->json('Unauthorized: You do not have permission to review purchase orders', 403);
            }
            
            $po = PurchaseOrder::findOrFail($id);
            $po->reviewed_by = auth()->id();
            $po->reviewed_at = now();
            $po->status = "reviewed";
            $po->save();

            // Notify users with "approve" or "reject" permission (next action = approve/reject)
            $po->load('supplier');
            $approvers = User::withPermission('purchase-order-approve')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            $rejectors = User::withPermission('purchase-order-reject')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            $recipients = $approvers->merge($rejectors)->unique('id');
            foreach ($recipients as $user) {
                $user->notify(new PurchaseOrderActionRequired($po, PurchaseOrderActionRequired::ACTION_READY_FOR_APPROVAL));
            }

            return response()->json('Purchase order has been marked for review');
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function rejectPO(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string|max:1000'
            ]);

            return DB::transaction(function () use ($id, $validated) {
                $po = PurchaseOrder::findOrFail($id);
                
                if (!$po->reviewed_by || !$po->reviewed_at) {
                    return response()->json('Purchase order must be reviewed before it can be rejected', 422);
                }

                if ($po->approved_at) {
                    return response()->json('Cannot reject an approved purchase order', 422);
                }

                // Update PO status
                $po->status = 'rejected';
                $po->rejected_by = auth()->id();
                $po->rejected_at = now();
                $po->rejection_reason = $validated['reason'];
                $po->save();

                return response()->json('Purchase order has been rejected');
            });
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function approvePO($id)
    {
        try {
            // Check permission
            if (!auth()->user()->hasPermission('purchase-order-approve')) {
                return response()->json('Unauthorized: You do not have permission to approve purchase orders', 403);
            }
            
            return DB::transaction(function () use ($id) {
                $po = PurchaseOrder::with(['items', 'approvedBy'])->findOrFail($id);
                
                if (!$po->reviewed_by || !$po->reviewed_at) {
                    return response()->json('Purchase order must be reviewed before it can be approved', 422);
                }

                if ($po->rejected_at) {
                    return response()->json('Cannot approve a rejected purchase order', 422);
                }

                // Update PO status
                $po->status = 'approved';
                $po->approved_by = auth()->id();
                $po->approved_at = now();
                $po->save();
                
                // Reset original quantity and UOM for all items
                foreach ($po->items as $item) {
                    $item->update([
                        'original_quantity' => null,
                        'original_uom' => null,
                        'edited_by' => null
                    ]);
                }

                // Reload the PO to get the fresh approvedBy relationship
                $po->load('approvedBy');

                return response()->json([
                    'message' => 'Purchase order has been approved and inventory has been updated',
                    'approved_at' => $po->approved_at,
                    'approved_by' => $po->approvedBy
                ]);
            });
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified supply from storage.
     */
    public function destroy(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // Adjust inventory
           $po = PurchaseOrder::find($id);
           if (in_array(strtolower($po->status ?? ''), ['approved', 'completed'])) {
            return response()->json('Approved or completed purchase orders cannot be deleted.', 403);
           }
           if ($po->status != 'pending') {
            return response()->json('This purchase order cannot be deleted because its status is ' . $po->status . '.', 403);
           }
           $po->items()->delete();
           $po->delete();
            DB::commit();
            return response()->json('Supply deleted successfully', 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function uploadDocument(Request $request, $id)
    {
        try {
            $po = PurchaseOrder::find($id);
            if (!$po) {
                return response()->json('Purchase order not found', 404);
            }

            $request->validate([
                'document' => 'required|file|mimes:pdf'
            ],[
                'document.required' => 'Document is required',
                'document.file' => 'Document must be a file',
                'document.mimes' => 'Document must be a PDF file'
            ]);

            $index = $po->documents()->count() + 1;
            $file = $request->file('document');
            $fileName = 'purchase_order_' . time() . '_' . $index . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('attachments/purchase_orders'), $fileName);

            $po->documents()->create([
                'purchase_order_id' => $po->id,
                'document_type' => 'purchase_order',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => '/attachments/purchase_orders/' . $fileName,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => filesize(public_path('attachments/purchase_orders/' . $fileName)),
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now()->toDateTimeString()
            ]);

            return response()->json('Document uploaded successfully', 200);

        } catch (\Throwable $th) {
            return response()->json('Upload failed: The uploaded file could not be saved.', 500);
        }
    }


    /**
     * Get items for a specific supply
     */
    public function getItems(Supply $supply)
    {
        return $supply->items()->with('product')->get();
    }

    /**
     * Update the status of a supply item.
     */
    public function updateItemStatus(Request $request, SupplyItem $item)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'status' => 'required|in:pending,approved,rejected'
            ]);

            $item->update([
                'status' => $validated['status']
            ]);

            // If approved, update inventory
            if ($validated['status'] === 'approved' && $item->status !== 'approved') {
                $inventory = Inventory::firstOrCreate(
                    [
                        'product_id' => $item->product_id,
                        'warehouse_id' => $item->supply->warehouse_id,
                        'batch_number' => $item->batch_number,
                    ],
                    [
                        'quantity' => 0,
                        'manufacturing_date' => $item->manufacturing_date,
                        'expiry_date' => $item->expiry_date,
                    ]
                );

                $inventory->quantity += $item->quantity;
                $inventory->save();

                // Fire inventory updated event
                event(new InventoryUpdated($inventory));
            }

            DB::commit();
            return response()->json(['message' => 'Item status updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update item status: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Approve or reject a supply item
     */
    public function approveItem(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            
            $validated = $request->validate([
                'status' => 'required|in:approved,rejected',
            ]);

            $item = SupplyItem::with(['supply', 'product'])->findOrFail($id);
            
            $item->update([
                'status' => $validated['status'],
                'approved_at' => now(),
                'approved_by' => auth()->id()
            ]);

            // If approved, update inventory
            if ($validated['status'] === 'approved') {
                // Check for existing inventory with same product and expiry date
                $inventory = Inventory::where('product_id', $item->product_id)
                    ->where('expiry_date', $item->expiry_date)
                    ->first();

                if ($inventory) {
                    // Update existing inventory quantity
                    $inventory->increment('quantity', $item->quantity);
                } else {
                    // Create new inventory record
                    Inventory::create([
                        'product_id' => $item->product_id,
                        'batch_number' => $item->batch_number,
                        'quantity' => $item->quantity,
                        'expiry_date' => $item->expiry_date,
                        'manufacturing_date' => $item->manufacturing_date,
                        'warehouse_id' => $item->supply->warehouse_id,
                        'created_by' => auth()->id()
                    ]);
                }
            }
            event(new InventoryUpdated());

            DB::commit();
            return response()->json(['message' => 'Item status updated successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update item status: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Approve or reject all pending items in a supply.
     */
    public function approveBulk(Request $request, Supply $supply)
    {
        try {
            return DB::transaction(function () use ($request, $supply) {
                $validated = $request->validate([
                    'status' => 'required|in:approved,rejected',
                    'notes' => 'nullable|string',
                ]);

                $items = $supply->items()->where('status', 'pending')->get();

                foreach ($items as $item) {
                    // Update the item with approval info
                    $item->update([
                        'status' => $validated['status'],
                        'approval_notes' => $validated['notes'],
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);

                    // If approved, update inventory
                    if ($validated['status'] === 'approved') {
                        $inventory = Inventory::firstOrNew([
                            'product_id' => $item->product_id,
                            'warehouse_id' => $supply->warehouse_id,
                            'batch_number' => $item->batch_number,
                        ]);

                        if (!$inventory->exists) {
                            $inventory->manufacturing_date = $item->manufacturing_date;
                            $inventory->expiry_date = $item->expiry_date;
                            $inventory->quantity = 0;
                        }

                        $inventory->quantity += $item->quantity;
                        $inventory->save();
                    }
                }

                return response()->json('Supply items ' . $validated['status'] . ' successfully', 200);
            });
        } catch (\Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function searchSuppliers(Request $request)
    {
        $query = $request->input('query');
        $suppliers = Supplier::query()
            ->select('id', 'name')
            ->when($query, function ($q) use ($query) {
                return $q->where('name', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(function ($supplier) {
                return [
                    'value' => $supplier->id,
                    'label' => $supplier->name
                ];
            });

        return response()->json($suppliers);
    }

    public function updatePurchaseOrder(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'supplier_id' => 'required|exists:suppliers,id',
                'po_number' => 'required|string',
                'original_po_no' => 'nullable|string',
                'po_date' => 'required|date',
                'expected_date' => 'required|date',
                'items' => 'required|array|min:1',                
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.id' => 'nullable|exists:purchase_order_items,id',
                'items.*.unit_cost' => 'required|numeric|min:0',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.total_cost' => 'required|numeric|min:0',
                'items.*.uom' => 'nullable|string',
                'notes' => 'nullable|string'
            ]);

            return DB::transaction(function () use ($validated, $id) {
                $po = PurchaseOrder::findOrFail($id);
                
                // Approved and completed POs cannot be edited
                if (in_array(strtolower($po->status ?? ''), ['approved', 'completed'])) {
                    throw new \Exception('Approved or completed purchase orders cannot be edited.');
                }
                
                // Update PO details
                $po->update([
                    'po_number' => $validated['po_number'],
                    'supplier_id' => $validated['supplier_id'],
                    'original_po_no' => $validated['original_po_no'],
                    'po_date' => $validated['po_date'],
                    'expected_date' => $validated['expected_date'] ?? null,
                    'notes' => $validated['notes'],
                    'updated_by' => auth()->id()
                ]);

                // Process each item individually to preserve original tracking
                foreach ($validated['items'] as $item) {
                    if (!isset($item['product_id'])) continue;
                    
                    $itemData = [
                        'purchase_order_id' => $po->id,
                        'id' => $item['id'] ?? null,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'],
                        'total_cost' => $item['total_cost'],
                        'uom' => $item['uom'] ?? null,
                        'updated_at' => now() // Force update by changing timestamp
                    ];
                    if (isset($item['id'])) {
                        $existingItem = PurchaseOrderItem::find($item['id']);
                        
                        // Check if quantity has changed
                        logger()->info('Quantity comparison - Current: ' . $existingItem->quantity . ', New: ' . $item['quantity'] . ', Original: ' . $existingItem->original_quantity);
                        
                        // Check if this is a rollback to original value
                        if ($existingItem->original_quantity !== null && (int)$item['quantity'] == (int)$existingItem->original_quantity) {
                            // Rollback detected - clear original_quantity
                            $itemData['original_quantity'] = null;
                            $itemData['edited_by'] = auth()->id();
                            logger()->info('Rolling back quantity to original, clearing original_quantity');
                        } elseif ($existingItem->quantity != $item['quantity']) {
                            // Quantity changed - capture original if not already set
                            if ($existingItem->original_quantity === null) {
                                $itemData['original_quantity'] = $existingItem->quantity;
                                logger()->info('Capturing original quantity: ' . $existingItem->quantity);
                            }
                            $itemData['edited_by'] = auth()->id();
                        } else {
                            logger()->info('No quantity change detected');
                        }
                        
                        if ($existingItem->original_uom !== null && (int)$item['uom'] == (int)$existingItem->original_uom) {
                            // Rollback detected - clear original_uom
                            $itemData['original_uom'] = null;
                            $itemData['edited_by'] = auth()->id();
                            logger()->info('Rolling back uom to original, clearing original_uom');
                        } elseif ($existingItem->uom != $item['uom']) {
                            // uom changed - capture original if not already set
                            if ($existingItem->original_uom === null) {
                                $itemData['original_uom'] = $existingItem->uom;
                                logger()->info('Capturing original uom: ' . $existingItem->uom);
                            }
                            $itemData['edited_by'] = auth()->id();
                        } else {
                            logger()->info('No uom change detected');
                        }

                        // Force update even if data hasn't changed to ensure original tracking is processed
                        $existingItem = PurchaseOrderItem::find($item['id']);
                        if ($existingItem) {
                            $existingItem->update($itemData);
                        } else {
                            PurchaseOrderItem::create($itemData);
                        }
                    }
                }

                return response()->json([
                    'message' => 'Purchase order updated successfully',
                    'purchase_order' => $po->fresh()->load('items.product', 'supplier')
                ], 200);
            });

        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function deletePurchaseOrder(Request $request, $id){
        try {
            $po = PurchaseOrder::find($id);
            if($po->status != 'pending'){
                return response()->json("This $po->po_number already approved and it can not be deleted", 500);
            }
            $po->items()->delete();
            $po->delete();
            return response()->json("Deleted succefyully", 200);

        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function deletePurchaseOrderItem($id)
    {
        try {
            $item = \App\Models\PurchaseOrderItem::findOrFail($id);
            $item->delete();
            return response()->json('Item removed successfully', 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function storeLocation(Request $request){
        try {
            $request->validate([
                'location' => 'required|string',
                'warehouse' => 'required|string'
            ]);
            
            $location = Location::create([
                'location' => $request->location,
                'warehouse' => $request->warehouse
            ]);
            
            return response()->json([
                'message' => 'Location created successfully',
                'location' => [
                    'id' => $location->id,
                    'location' => $location->location,
                    'warehouse' => $location->warehouse
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function showPK(Request $request){
        if (!auth()->user()->hasPermission('packing-list-view')) {
            abort(403, 'Unauthorized: You do not have permission to view packing lists.');
        }
        $query = PackingList::with([
            'items.product.category',
            'items.product.dosage', 
            'purchaseOrder.supplier',
            'purchaseOrder.items',
            'confirmedBy',
            'approvedBy',
            'rejectedBy',
            'reviewedBy',
            'backOrder'
        ])
        ->select('packing_lists.*')
        ->selectRaw('
            CASE 
                WHEN (
                    SELECT COALESCE(SUM(poi.quantity), 0) 
                    FROM purchase_order_items poi 
                    WHERE poi.purchase_order_id = packing_lists.purchase_order_id
                ) > 0 
                THEN CONCAT(
                    ROUND(
                        (
                            SELECT COALESCE(SUM(pli.quantity), 0) 
                            FROM packing_list_items pli 
                            WHERE pli.packing_list_id = packing_lists.id
                        ) / (
                            SELECT COALESCE(SUM(poi.quantity), 0) 
                            FROM purchase_order_items poi 
                            WHERE poi.purchase_order_id = packing_lists.purchase_order_id
                        ) * 100, 2
                    ), "%"
                )
                ELSE "0%" 
            END as fulfillment_rate
        ');

        // Apply filters
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('packing_list_number', 'like', '%' . $request->search . '%')
                  ->orWhere('ref_no', 'like', '%' . $request->search . '%')
                  ->orWhereHas('purchaseOrder.supplier', function($sq) use ($request) {
                      $sq->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->filled('supplier')) {
            $query->whereHas('purchaseOrder.supplier', function($q) use ($request) {
                $q->where('id', $request->supplier);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('pk_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('pk_date', '<=', $request->date_to);
        }

        $packingLists = $query->orderBy('created_at', 'desc')->get();

        // Calculate fulfillment rate for each packing list
        $packingLists->each(function($packingList) {
            $totalOrdered = $packingList->purchaseOrder->items->sum('quantity');
            $totalReceived = $packingList->items->sum('quantity');
            
            if ($totalOrdered > 0) {
                $fulfillmentRate = round(($totalReceived / $totalOrdered) * 100, 2);
                $packingList->fulfillment_rate = $fulfillmentRate . '%';
            } else {
                $packingList->fulfillment_rate = '0%';
            }
        });

        // Get all suppliers for the filter dropdown
        $suppliers = Supplier::pluck('name')->toArray();

        return inertia('Supplies/PackingList/Show', [
            'packingLists' => PackingListResource::collection($packingLists),
            'suppliers' => $suppliers,
            'filters' => $request->only('search', 'supplier', 'status', 'date_from', 'date_to')
        ]);
    }

    public function show(Request $request){
        $query = Supplier::query();
        if($request->filled('search')){
            $query->where('name', 'like', '%' . $request->search . '%') 
                ->orWhere('contact_person', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%')
                ->orWhere('phone', 'like', '%' . $request->search . '%')
                ->orWhere('address', 'like', '%' . $request->search . '%');
        }

        if($request->filled('status') && $request->status != 'all'){
            $query->where('status', $request->status);
        }

        $supplier = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $supplier->setPath(url()->current());

        return inertia('Supplies/Show', [
            'suppliers' => SupplierResource::collection($supplier),
            'filters' => $request->only('search', 'per_page', 'status')
        ]);
    }

    public function showBackOrder(Request $request){
        $query = BackOrder::query();

        if($request->filled('search')){
            $query->whereHas('packingList', function($q) use ($request){
                $q->where('packing_list_number', 'like', '%' . $request->search . '%')
                    ->orWhere('ref_no', 'like', '%' . $request->search . '%');
            })
            ->orWhere('back_order_number', 'like', '%' . $request->search . '%');
        }
        if($request->filled('warehouse')){
            $query->where('reported_by', $request->warehouse);
        }
        if($request->filled('facility')){
            $query->where('reported_by', $request->facility);
        }
        if($request->filled('supplier')){
            $query->whereHas('packingList.purchaseOrder.supplier', function($q) use ($request){
                $q->where('name', 'like', $request->supplier);
            });
        }
        // with
        $query = $query->with('packingList.purchaseOrder.supplier')->latest();
        $history = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $history->setPath(url()->current());

        return inertia('Supplies/ShowBackOrder', [
            'history' => BackOrderHistoryResource::collection($history),
            'filters' => $request->only('search', 'per_page', 'warehouse', 'facility','supplier'),
            'warehouses' => Warehouse::pluck('name')->toArray(),
            'facilities' => Facility::pluck('name')->toArray(),
            'suppliers' => Supplier::pluck('name')->toArray()
        ]);
    }

    public function editPK($id)
    {
        if (!auth()->user()->hasPermission('packing-list-edit') && !auth()->user()->hasPermission('packing-list-update') && !auth()->user()->hasPermission('packing-list-view')) {
            abort(403, 'You do not have permission to view or edit packing lists.');
        }

        $packing_list = PackingList::with([
            'items.warehouse:id,name',
            'items.product:id,name',
            'items.purchaseOrderItem:id,quantity',
            'items.differences',
            'purchaseOrder.supplier',
            'approvedBy:id,name',
            'confirmedBy:id,name',
            'reviewedBy:id,name',
            'rejectedBy:id,name',
        ])
        ->find($id);

        $warehouses = Warehouse::select('id', 'name')->get();
        $locations = Location::select('id', 'location')->get();

        return Inertia::render('Supplies/EditPK', [
            'packing_list' => $packing_list,
            'warehouses' => $warehouses,
            'locations' => $locations
        ]);
    }

    public function storePKLocation(Request $request)
    {
        try {
            $request->validate([
                'id' => 'nullable',
                'location' => 'required|string|unique:locations,location,' . $request->id
            ]);
    
            $location = Location::updateOrCreate([
                'id' => $request->id
            ],[
                'location' => $request->location
            ]);
    
            return response()->json([
                'message' => 'Location created successfully',
                'location' => $location
            ]);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function updatePK(Request $request)
    {
        if (!auth()->user()->hasPermission('packing-list-edit') && !auth()->user()->hasPermission('packing-list-update')) {
            return response()->json('Unauthorized: You do not have permission to edit or update packing lists', 403);
        }

        try {
            return DB::transaction(function() use ($request){
                $request->validate([
                    'id' => 'required',
                    'pk_date' => 'required|date',
                    'packing_list_number' => 'required',
                    'purchase_order_id' => 'required|exists:purchase_orders,id',
                    'ref_no' => 'nullable',
                    'items' => 'required|array',
                    'items.*.id' => 'required|exists:packing_list_items,id',
                    'items.*.product_id' => 'required|exists:products,id',
                    'items.*.warehouse_id' => 'required|exists:warehouses,id',
                    'items.*.po_item_id' => 'required|exists:purchase_order_items,id',
                    'items.*.barcode' => 'nullable',
                    'items.*.batch_number' => 'required',
                    'items.*.location' => 'required|string',
                    'items.*.quantity' => 'required|numeric|min:0',
                    'items.*.uom' => 'required',
                    'items.*.expire_date' => 'required|date',
                    'items.*.unit_cost' => 'required|numeric|min:0',
                    'items.*.total_cost' => 'required|numeric|min:0',
                    'items.*.differences' => 'nullable|array',
                    'items.*.differences.*.id' => 'nullable|exists:packing_list_differences,id',
                    'items.*.differences.*.quantity' => 'required_with:items.*.differences|numeric|min:0',
                    'items.*.differences.*.status' => 'required_with:items.*.differences|in:Missing,Damaged,Lost,Expired,Low quality',
                    'items.*.differences.*.note' => 'nullable|string'
                ]);

                // Update main packing list
                $packingList = PackingList::findOrFail($request->id);
                $packingList->update([
                    'pk_date' => $request->pk_date,
                    'ref_no' => $request->ref_no,
                    'confirmed_by' => auth()->user()->id,
                    'confirmed_at' => now(),
                ]);

                // Check if any items have differences to determine if we need a BackOrder
                $hasBackOrderItems = collect($request->items)
                    ->filter(function($item) {
                        return !empty($item['differences']);
                    })
                    ->isNotEmpty();

                $backOrder = null;
                if ($hasBackOrderItems) {
                    // Create or update parent BackOrder
                    $backOrder = BackOrder::firstOrCreate(
                        ['packing_list_id' => $packingList->id],
                        [
                            'back_order_date' => now()->toDateString(),
                            'created_by' => auth()->id(),
                            'status' => 'pending',
                            'source_type' => 'packing_list',
                            'reported_by' => auth()->user()->load('warehouse')->warehouse->name ?? 'Unknown Warehouse'
                        ]
                    );
                } else {
                    // If no differences, delete existing BackOrder if exists
                    $existingBackOrder = BackOrder::where('packing_list_id', $packingList->id)->first();
                    if ($existingBackOrder) {
                        $existingBackOrder->delete(); // This will cascade delete differences
                    }
                }

                // Update each item
                foreach($request->items as $item) {
                    $packingListItem = $packingList->items()->findOrFail($item['id']);
                    
                    $packingListItem->update([
                        'warehouse_id' => $item['warehouse_id'],
                        'location' => $item['location'],
                        'quantity' => $item['quantity'],
                        'batch_number' => $item['batch_number'],
                        'barcode' => $item['barcode'],
                        'expire_date' => $item['expire_date'],
                        'unit_cost' => $item['unit_cost'],
                        'total_cost' => $item['total_cost'],
                    ]);

                    // Handle differences
                    if (empty($item['differences'])) {
                        // If no differences provided, delete existing ones
                        $packingListItem->differences()->delete();
                    } else {
                        // Get existing difference IDs
                        $existingDiffIds = $packingListItem->differences()->pluck('id')->toArray();
                        $newDiffIds = [];

                        // Update or create differences
                        foreach ($item['differences'] as $diff) {
                            $difference = $packingListItem->differences()
                                ->updateOrCreate(
                                    ['id' => $diff['id'] ?? null],
                                    [
                                        'back_order_id' => $backOrder->id,
                                        'quantity' => $diff['quantity'],
                                        'status' => $diff['status'],
                                        'notes' => $diff['notes'] ?? null,
                                        'product_id' => $item['product_id']
                                    ]
                                );
                            $newDiffIds[] = $difference->id;
                        }

                        // Delete differences that weren't updated or created
                        $packingListItem->differences()
                            ->whereIn('id', array_diff($existingDiffIds, $newDiffIds))
                            ->delete();
                    }
                }

                // Update BackOrder totals if it exists
                if ($backOrder) {
                    $backOrder->updateTotals();
                }

                return response()->json('Packing list updated successfully', 200);
            });
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }

    }    

    public function reviewPK(Request $request)
    {
        try {
            if (!auth()->user()->hasPermission('packing-list-review')) {
                return response()->json('Unauthorized: You do not have permission to review packing lists', 403);
            }

            $request->validate([
                'id' => 'required|exists:packing_lists,id',
                'status' => 'required|in:reviewed',
            ]);

            $packingList = PackingList::findOrFail($request->id);

            if ($packingList->reviewed_at) {
                return response()->json('Packing list has already been reviewed', 422);
            }
            if ($packingList->approved_at) {
                return response()->json('Packing list is already approved', 422);
            }
            if ($packingList->rejected_at) {
                return response()->json('Cannot review a rejected packing list', 422);
            }

            $packingList->update([
                'status' => 'reviewed',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
            $packingList->load('reviewedBy');

            // Notify users with approve/reject permission (next action)
            $approvers = User::withPermission('packing-list-approve')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            $rejectors = User::withPermission('packing-list-reject')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            $recipients = $approvers->merge($rejectors)->unique('id');
            foreach ($recipients as $user) {
                $user->notify(new PackingListActionRequired($packingList, PackingListActionRequired::ACTION_READY_FOR_APPROVAL));
            }

            return response()->json([
                'message' => 'Packing list has been marked for review',
                'reviewed_at' => $packingList->reviewed_at->toIso8601String(),
                'reviewed_by' => $packingList->reviewedBy,
            ]);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function rejectPK(Request $request)
    {
        try {
            if (!auth()->user()->hasPermission('packing-list-reject')) {
                return response()->json('Unauthorized: You do not have permission to reject packing lists', 403);
            }

            $validated = $request->validate([
                'id' => 'required|exists:packing_lists,id',
                'status' => 'required|in:rejected',
                'rejection_reason' => 'required|string|max:1000',
            ]);

            $packingList = PackingList::findOrFail($request->id);

            if (!$packingList->reviewed_at) {
                return response()->json('Packing list must be reviewed before it can be rejected', 422);
            }
            if ($packingList->approved_at) {
                return response()->json('Cannot reject an approved packing list', 422);
            }

            $packingList->update([
                'status' => 'rejected',
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);
            $packingList->load('rejectedBy');

            return response()->json([
                'message' => 'Packing list has been rejected',
                'rejected_at' => $packingList->rejected_at->toIso8601String(),
                'rejected_by' => $packingList->rejectedBy,
            ]);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    // approve packing list and release to the inventory
    public function approvePK(Request $request)
    {
        if (!auth()->user()->hasPermission('packing-list-approve')) {
            return response()->json('Unauthorized: You do not have permission to approve packing lists', 403);
        }

        $request->validate([
            'id' => 'required|exists:packing_lists,id',
            'status' => 'required|in:approved',
            'items' => 'required|array',
        ]);

        $packingList = PackingList::with('items.purchaseOrderItem')->findOrFail($request->id);

        if (!$packingList->reviewed_at) {
            return response()->json('Packing list must be reviewed before it can be approved', 422);
        }
        if ($packingList->rejected_at) {
            return response()->json('Cannot approve a rejected packing list', 422);
        }

        DB::beginTransaction();

        try {

            foreach ($request->items as $itemData) {
                $pli = PackingListItem::findOrFail($itemData['id']);
                $recvQty = (int)$itemData['quantity'];
                if ($recvQty <= 0) continue;

                // 1️⃣ Upsert Inventory
                $inventory = Inventory::firstOrCreate(
                    [
                        'product_id' => $pli->product_id,
                    ],
                    [
                        'quantity' => 0,
                    ]
                );
                $inventory->increment('quantity', $recvQty);

                // 2️⃣ Create or update InventoryItem batch record
                $inventoryItem = InventoryItem::firstOrNew([
                    'inventory_id'   => $inventory->id,
                    'batch_number'   => $pli->batch_number,
                    'warehouse_id'   => $pli->warehouse_id,
                    'source'         => 'PSI',
                ]);

                // fill or update fields
                $inventoryItem->fill([
                    'product_id'    => $pli->product_id,
                    'quantity'      => ($inventoryItem->exists ? $inventoryItem->quantity : 0) + $recvQty,
                    'expiry_date'   => $pli->expire_date,
                    'barcode'       => $pli->barcode,
                    'location'      => $pli->location,
                    'uom'           => $pli->uom,
                    'source'        => 'PSI',
                    'unit_cost'     => $pli->unit_cost,
                    'total_cost'    => $pli->unit_cost * $inventoryItem->quantity,
                ]);
                $inventoryItem->save();

                // 3️⃣ Track received quantities
                ReceivedQuantity::create([
                    'quantity'         => $recvQty,
                    'received_by'      => auth()->id(),
                    'received_at'      => now(),
                    'product_id'       => $pli->product_id,
                    'packing_list_id'  => $pli->packing_list_id,
                    'expiry_date'      => $pli->expire_date,
                    'uom'              => $pli->uom,
                    'source'           => 'PSI',
                    'warehouse_id'     => $pli->warehouse_id,
                    'barcode'          => $pli->barcode,
                    'batch_number'     => $pli->batch_number,
                    'unit_cost'        => $pli->unit_cost,
                    'total_cost'       => $pli->unit_cost * $recvQty,
                ]);

                // 4️⃣ Check for quantity differences
                $poQty = $pli->purchaseOrderItem->quantity ?? 0;
                $diff = $pli->quantity - $poQty;
                if ($diff > 0) {
                    PackingListDifference::firstOrCreate(
                        [
                            'packing_list_item_id' => $pli->id,
                            'status'              => 'Missing'
                        ],
                        [
                            'product_id' => $pli->product_id,
                            'quantity'   => $diff,
                        ]
                    );
                }
            }

            // 5️⃣ Update packing list status
            $packingList->update([
                'status'         => $request->status,
                'approved_by'    => auth()->id(),
                'approved_at'    => now(),
            ]);

            // 6️⃣ Optionally close PO if fully received
            $po = PurchaseOrder::with('items', 'packingLists')
                ->find($packingList->purchase_order_id);

            if ($po) {
                $poQtys = $po->items->groupBy('product_id')
                    ->map(fn($it) => $it->sum('quantity'));
                $plQtys = $po->packingLists->where('status', 'approved')
                    ->flatMap->items
                    ->groupBy('product_id')
                    ->map(fn($it) => $it->sum('quantity'));

                if ($poQtys->every(fn($qty, $pid) => $plQtys->get($pid, 0) >= $qty)) {
                    $po->update(['status' => 'completed']);
                }
            }

            DB::commit();

            return response()->json(['message' => 'Packing list approved and inventory updated'], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $packingList = PackingList::findOrFail($id);

            $validated = $request->validate([
                'batch_number' => 'required|string',
                'location' => 'required|string',
                'expire_date' => 'required',
                'quantity' => 'required|numeric|min:0'
            ]);
            $packingList->update([
                'batch_number' => $validated['batch_number'],
                'location' => $validated['location'],
                'quantity' => $validated['quantity'],
                'expire_date' => $validated['expire_date']
            ]);

            return redirect()->route('supplies.showPK')
                ->with('success', 'Packing list updated successfully');
        } catch (\Exception $e) {
            return redirect()->route('supplies.showPK')
                ->with('error', 'Error updating packing list: ' . $e->getMessage());
        }
    }

    public function locationsShow(Request $request){
        $locations = Location::get();
        return inertia("Supplies/Location", [
            "locations" => $locations
        ]);
    }

    public function locationEdit(Request $request, $id){
        $location = Location::find($id);
        return inertia("Supplies/LocationEdit", [
            "location" => $location
        ]);
    }

    public function loadItems($id){
        try {
            $items = PurchaseOrderItem::where('purchase_order_id', $id)->get();
            return response()->json($items, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function deletePackingListDiff($id)
    {
        if (!auth()->user()->hasPermission('packing-list-delete')) {
            return response()->json('Unauthorized: You do not have permission to delete packing list items', 403);
        }
        try {
            // Get the difference record first to get its packing_list_id
            $difference = DB::table('packing_list_differences')->where('id', $id)->first();
            if (!$difference) {
                return response()->json(['message' => 'Difference not found'], 404);
            }

            // Get the packing list to get the purchase order item id
            $packingList = DB::table('packing_lists')->where('id', $difference->packing_list_id)->first();
            if (!$packingList) {
                return response()->json(['message' => 'Packing list not found'], 404);
            }

            // Delete the difference
            DB::table('packing_list_differences')->where('id', $id)->delete();

            // Get all remaining differences for ALL packing lists of this purchase order item
            $differences = DB::table('packing_list_differences as pld')
                ->join('packing_lists as pl', 'pl.id', '=', 'pld.packing_list_id')
                ->where('pl.po_id', $packingList->po_id)
                ->select('pld.*')
                ->get()
                ->map(function($diff) {
                    return [
                        'id' => $diff->id,
                        'quantity' => $diff->quantity,
                        'status' => $diff->status,
                        'created_at' => $diff->created_at
                    ];
                })
                ->values()
                ->toArray();

            return response()->json([
                'message' => 'Difference deleted successfully',
                'differences' => $differences
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error deleting difference: ' . $e->getMessage()], 500);
        }
    }

    public function uploadPackingListDocument(Request $request, $id)
    {
        try {
            $packingList = PackingList::find($id);
            if (!$packingList) {
                return response()->json('Packing list not found', 404);
            }

            $request->validate([
                'document' => 'required|file|mimes:pdf'
            ],[
                'document.required' => 'Document is required',
                'document.file' => 'Document must be a file',
                'document.mimes' => 'Document must be a PDF file'
            ]);

            $index = $packingList->documents()->count() + 1;
            $file = $request->file('document');
            $fileName = 'packing_list_' . time() . '_' . $index . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('attachments/packing_lists'), $fileName);

            $document = $packingList->documents()->create([
                'packing_list_id' => $packingList->id,
                'document_type' => 'packing_list',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => '/attachments/packing_lists/' . $fileName,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => filesize(public_path('attachments/packing_lists/' . $fileName)),
                'uploaded_by' => auth()->id()
            ]);

            // Load the uploader relationship
            $document->load('uploader:id,name');

            return response()->json([
                'message' => 'Document uploaded successfully',
                'document' => $document
            ], 200);

        } catch (\Throwable $th) {
            return response()->json('Upload failed: ' . $th->getMessage(), 500);
        }
    }

    public function listBackOrders()
    {
        $backOrders = \App\Models\BackOrder::with([
            'packingList.purchaseOrder.supplier',
            'creator',
        ])->get();
        return response()->json($backOrders);
    }

    public function getBackOrderHistories($backOrderId)
    {
        try {
            $histories = BackOrderHistory::with(['product.dosage','product.category', 'performer'])
            ->where('back_order_id', $backOrderId)
            ->get();
        return response()->json($histories, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function uploadBackOrderAttachment(Request $request, $backOrderId)
    {
        $request->validate([
            'attachments' => 'required|array',
            'attachments.*' => 'file|mimes:pdf|max:10240', // 10MB max per file
        ]);

        $backOrder = \App\Models\BackOrder::findOrFail($backOrderId);
        $existing = $backOrder->attach_documents ?? [];
        $newFiles = [];
        foreach ($request->file('attachments') as $file) {
            $fileName = 'backorder_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('attachments/backorders'), $fileName);
            $newFiles[] = [
                'name' => $file->getClientOriginalName(),
                'path' => '/attachments/backorders/' . $fileName,
                'type' => $file->getClientMimeType(),
                'size' => filesize(public_path('attachments/backorders/' . $fileName)),
                'uploaded_at' => now()->toDateTimeString()
            ];
        }
        $backOrder->attach_documents = array_merge($existing, $newFiles);
        $backOrder->save();
        return response()->json(['message' => 'Attachments uploaded successfully', 'files' => $backOrder->attach_documents]);
    }

    public function deleteBackOrderAttachment(Request $request, $backOrderId)
    {
        $request->validate(['file_path' => 'required|string']);
        $backOrder = \App\Models\BackOrder::findOrFail($backOrderId);
        $files = $backOrder->attach_documents ?? [];
        $files = array_filter($files, function($file) use ($request) {
            if ($file['path'] === $request->file_path) {
                $fullPath = public_path($file['path']);
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
                return false;
            }
            return true;
        });
        $backOrder->attach_documents = array_values($files);
        $backOrder->save();
        return response()->json(['message' => 'Attachment deleted successfully', 'files' => $backOrder->attach_documents]);
    }

    /**
     * Update inventory based on the back order type
     */
    private function updateInventoryForReceivedBackorder($receivedBackorder, $packingListItem, $receivedQuantity)
    {
        try {
            $backOrder = $receivedBackorder->backOrder;
            
            if (!$backOrder) {
                return;
            }

            // Determine the type and handle inventory accordingly
            if ($backOrder->packing_list_id) {
                // Packing List: Use Inventory (warehouse inventory)
                $this->handlePackingListInventoryUpdate($receivedBackorder, $packingListItem, $receivedQuantity);
            } elseif ($backOrder->order_id) {
                // Order: Use FacilityInventory (facility inventory)
                $this->handleOrderInventoryUpdate($receivedBackorder, $packingListItem, $receivedQuantity);
            } elseif ($backOrder->transfer_id) {
                // Transfer: Check destination to determine inventory type
                $transfer = \App\Models\Transfer::find($backOrder->transfer_id);
                if ($transfer) {
                    if ($transfer->to_facility_id) {
                        // Transfer to facility: Use FacilityInventory
                        $this->handleFacilityTransferInventoryUpdate($receivedBackorder, $packingListItem, $receivedQuantity, $transfer);
                    } else {
                        // Transfer to warehouse: Use Inventory
                        $this->handleWarehouseTransferInventoryUpdate($receivedBackorder, $packingListItem, $receivedQuantity, $transfer);
                    }
                }
            }

        } catch (\Exception $e) {
            logger()->error('Error updating inventory for received backorder', [
                'received_backorder_id' => $receivedBackorder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle packing list inventory update (warehouse inventory)
     */
    private function handlePackingListInventoryUpdate($receivedBackorder, $packingListItem, $receivedQuantity)
    {
        $warehouseId = $packingListItem->warehouse_id;
        $productId = $packingListItem->product_id;

        if (!$warehouseId || !$productId) {
            throw new \Exception('Warehouse ID and Product ID are required for packing list inventory update');
        }

        // Update or create main inventory
        $inventory = Inventory::firstOrCreate([
            'product_id' => $productId,
        ], [
            'quantity' => 0,
        ]);

        $inventory->increment('quantity', $receivedQuantity);
        $inventory->save();

        // Update or create inventory item with batch details
        $inventoryItem = InventoryItem::where('inventory_id', $inventory->id)
            ->where('batch_number', $packingListItem->batch_number)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($inventoryItem) {
            $inventoryItem->increment('quantity', $receivedQuantity);
            $inventoryItem->save();
        } else {
            $inventoryItem = InventoryItem::create([
                'inventory_id' => $inventory->id,
                'quantity' => $receivedQuantity,
                'batch_number' => $packingListItem->batch_number,
                'expiry_date' => $packingListItem->expire_date,
                'barcode' => $packingListItem->barcode,
                'warehouse_id' => $warehouseId,
                'location' => $packingListItem->location,
                'unit_cost' => $packingListItem->unit_cost,
                'total_cost' => $packingListItem->unit_cost * $receivedQuantity,
                'uom' => $packingListItem->uom,
                'status' => 'active'
            ]);
        }

        // Validate required fields before creating received quantity record
        if (!$receivedQuantity || $receivedQuantity <= 0) {
            throw new \Exception('Invalid quantity for received quantity record');
        }

        // Create received quantity record with proper validation
        $receivedQuantityData = [
            'quantity' => $receivedQuantity,
            'received_by' => auth()->id(),
            'received_at' => now(),
            'packing_list_id' => $receivedBackorder->packing_list_id,
            'product_id' => $productId,
            'uom' => $packingListItem->uom ?? 'N/A',
            'barcode' => $packingListItem->barcode ?? 'N/A',
            'batch_number' => $packingListItem->batch_number ?? 'N/A',
            'warehouse_id' => $warehouseId,
            'expiry_date' => $packingListItem->expire_date ?? now()->addYears(1)->toDateString(),
            'unit_cost' => $packingListItem->unit_cost ?? 0,
            'total_cost' => ($packingListItem->unit_cost ?? 0) * $receivedQuantity
        ];

        ReceivedQuantity::create($receivedQuantityData);
    }

    /**
     * Handle order inventory update (facility inventory)
     */
    private function handleOrderInventoryUpdate($receivedBackorder, $packingListItem, $receivedQuantity)
    {
        $facilityId = $receivedBackorder->facility_id;
        $productId = $packingListItem->product_id;

        if (!$facilityId || !$productId) {
            throw new \Exception('Facility ID and Product ID are required for order inventory update');
        }

        // Update or create facility inventory
        $facilityInventory = \App\Models\FacilityInventory::firstOrCreate([
            'product_id' => $productId,
            'facility_id' => $facilityId,
        ], [
            'quantity' => 0,
        ]);

        $facilityInventory->increment('quantity', $receivedQuantity);
        $facilityInventory->save();

        // Update or create facility inventory item
        $facilityInventoryItem = \App\Models\FacilityInventoryItem::where('facility_inventory_id', $facilityInventory->id)
            ->where('batch_number', $packingListItem->batch_number)
            ->first();

        if ($facilityInventoryItem) {
            $facilityInventoryItem->increment('quantity', $receivedQuantity);
            $facilityInventoryItem->save();
        } else {
            $facilityInventoryItem = \App\Models\FacilityInventoryItem::create([
                'facility_inventory_id' => $facilityInventory->id,
                'product_id' => $productId,
                'quantity' => $receivedQuantity,
                'batch_number' => $packingListItem->batch_number,
                'expiry_date' => $packingListItem->expire_date,
                'barcode' => $packingListItem->barcode,
                'uom' => $packingListItem->uom,
                'unit_cost' => $packingListItem->unit_cost,
                'total_cost' => $packingListItem->unit_cost * $receivedQuantity,
                'notes' => 'Received from backorder'
            ]);
        }

        // Create facility inventory movement record
        $orderItem = \App\Models\OrderItem::where('order_id', $receivedBackorder->order_id)
            ->where('product_id', $productId)
            ->first();

        if ($orderItem) {
            $movementData = [
                'facility_id' => $facilityId,
                'product_id' => $productId,
                'source_type' => 'order',
                'source_id' => $receivedBackorder->order_id,
                'source_item_id' => $orderItem->id,
                'facility_received_quantity' => $receivedQuantity,
                'batch_number' => $packingListItem->batch_number,
                'expiry_date' => $packingListItem->expire_date,
                'barcode' => $packingListItem->barcode,
                'uom' => $packingListItem->uom,
                'movement_date' => now(),
                'reference_number' => $receivedBackorder->received_backorder_number,
                'notes' => 'Received from backorder',
            ];

            \App\Models\FacilityInventoryMovement::recordFacilityReceived($movementData);
        }
    }

    /**
     * Handle facility transfer inventory update (facility inventory)
     */
    private function handleFacilityTransferInventoryUpdate($receivedBackorder, $packingListItem, $receivedQuantity, $transfer)
    {
        $facilityId = $transfer->to_facility_id;
        $productId = $packingListItem->product_id;

        if (!$facilityId || !$productId) {
            throw new \Exception('Facility ID and Product ID are required for facility transfer inventory update');
        }

        // Update or create facility inventory
        $facilityInventory = \App\Models\FacilityInventory::firstOrCreate([
            'product_id' => $productId,
            'facility_id' => $facilityId,
        ], [
            'quantity' => 0,
        ]);

        $facilityInventory->increment('quantity', $receivedQuantity);
        $facilityInventory->save();

        // Update or create facility inventory item
        $facilityInventoryItem = \App\Models\FacilityInventoryItem::where('facility_inventory_id', $facilityInventory->id)
            ->where('batch_number', $packingListItem->batch_number)
            ->first();

        if ($facilityInventoryItem) {
            $facilityInventoryItem->increment('quantity', $receivedQuantity);
            $facilityInventoryItem->save();
        } else {
            $facilityInventoryItem = \App\Models\FacilityInventoryItem::create([
                'facility_inventory_id' => $facilityInventory->id,
                'product_id' => $productId,
                'quantity' => $receivedQuantity,
                'batch_number' => $packingListItem->batch_number,
                'expiry_date' => $packingListItem->expire_date,
                'barcode' => $packingListItem->barcode,
                'uom' => $packingListItem->uom,
                'unit_cost' => $packingListItem->unit_cost,
                'total_cost' => $packingListItem->unit_cost * $receivedQuantity,
                'notes' => 'Received from transfer backorder'
            ]);
        }

        // Create facility inventory movement record
        $transferItem = \App\Models\TransferItem::where('transfer_id', $receivedBackorder->transfer_id)
            ->where('product_id', $productId)
            ->first();

        if ($transferItem) {
            $movementData = [
                'facility_id' => $facilityId,
                'product_id' => $productId,
                'source_type' => 'transfer',
                'source_id' => $receivedBackorder->transfer_id,
                'source_item_id' => $transferItem->id,
                'facility_received_quantity' => $receivedQuantity,
                'batch_number' => $packingListItem->batch_number,
                'expiry_date' => $packingListItem->expire_date,
                'barcode' => $packingListItem->barcode,
                'uom' => $packingListItem->uom,
                'movement_date' => now(),
                'reference_number' => $receivedBackorder->received_backorder_number,
                'notes' => 'Received from transfer backorder',
            ];

            \App\Models\FacilityInventoryMovement::recordFacilityReceived($movementData);
        }
    }

    /**
     * Handle warehouse transfer inventory update (warehouse inventory)
     */
    private function handleWarehouseTransferInventoryUpdate($receivedBackorder, $packingListItem, $receivedQuantity, $transfer)
    {
        $warehouseId = $transfer->to_warehouse_id;
        $productId = $packingListItem->product_id;

        if (!$warehouseId || !$productId) {
            throw new \Exception('Warehouse ID and Product ID are required for warehouse transfer inventory update');
        }

        // Update or create main inventory
        $inventory = Inventory::firstOrCreate([
            'product_id' => $productId,
        ], [
            'quantity' => 0,
        ]);

        $inventory->increment('quantity', $receivedQuantity);
        $inventory->save();

        // Update or create inventory item with batch details
        $inventoryItem = InventoryItem::where('inventory_id', $inventory->id)
            ->where('batch_number', $packingListItem->batch_number)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($inventoryItem) {
            $inventoryItem->increment('quantity', $receivedQuantity);
            $inventoryItem->save();
        } else {
            $inventoryItem = InventoryItem::create([
                'inventory_id' => $inventory->id,
                'quantity' => $receivedQuantity,
                'batch_number' => $packingListItem->batch_number,
                'expiry_date' => $packingListItem->expire_date,
                'barcode' => $packingListItem->barcode,
                'warehouse_id' => $warehouseId,
                'location' => $packingListItem->location,
                'unit_cost' => $packingListItem->unit_cost,
                'total_cost' => $packingListItem->unit_cost * $receivedQuantity,
                'uom' => $packingListItem->uom,
                'status' => 'active'
            ]);
        }

        // Validate required fields before creating received quantity record
        if (!$receivedQuantity || $receivedQuantity <= 0) {
            throw new \Exception('Invalid quantity for received quantity record');
        }

        // Create received quantity record with proper validation
        $receivedQuantityData = [
            'quantity' => $receivedQuantity,
            'received_by' => auth()->id(),
            'received_at' => now(),
            'transfer_id' => $receivedBackorder->transfer_id,
            'product_id' => $productId,
            'uom' => $packingListItem->uom ?? 'N/A',
            'barcode' => $packingListItem->barcode ?? 'N/A',
            'batch_number' => $packingListItem->batch_number ?? 'N/A',
            'warehouse_id' => $warehouseId,
            'expiry_date' => $packingListItem->expire_date ?? now()->addYears(1)->toDateString(),
            'unit_cost' => $packingListItem->unit_cost ?? 0,
            'total_cost' => ($packingListItem->unit_cost ?? 0) * $receivedQuantity
        ];

        ReceivedQuantity::create($receivedQuantityData);
    }
}
