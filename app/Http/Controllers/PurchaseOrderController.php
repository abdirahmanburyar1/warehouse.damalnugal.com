<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\BackOrder;
use App\Models\PackingList;
use App\Models\Warehouse;
use App\Models\Approval;
use App\Imports\PurchaseOrderItemsImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\ReceivedGoodsNote;
use App\Http\Resources\PurchaseOrderResource;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::query()
            ->with(['supplier', 'po_items', 'creator', 'updater']);

        // Apply search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('po_items', function ($q) use ($search) {
                        $q->where('item_description', 'like', "%{$search}%");
                        $q->orWhere('item_code', 'like', "%{$search}%");
                    });
            });
        }

        // Apply status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Apply date range filter
        if ($startDate = $request->input('start_date')) {
            $query->whereDate('po_date', '>=', $startDate);
        }
        if ($endDate = $request->input('end_date')) {
            $query->whereDate('po_date', '<=', $endDate);
        }

        // Apply sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $purchaseOrders = $query->paginate(5);

        $purchaseOrders->setPath(route('purchase-orders.index'));



        return Inertia::render('PurchaseOrder/Index', [
            'purchase_orders' => $purchaseOrders,
            // 'purchase_orders' => PurchaseOrderResource::collection($purchaseOrders),
            'suppliers' => Supplier::select('id', 'name')->get(),
            'products' => Product::select('id', 'name')->get(),
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'po_number' => 'nullable|string|unique:purchase_orders,po_number,' . $request->input('id'),
                'supplier_id' => 'required|exists:suppliers,id',
                'po_date' => 'required|date',
                'expected_date' => 'nullable|date',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            // Generate PO number if not provided
            if (!$request->filled('po_number')) {
                $latestPo = PurchaseOrder::latest()->first();
                $lastNumber = $latestPo ? intval(substr($latestPo->po_number, 2)) : 0;
                $request->merge(['po_number' => 'PO' . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT)]);
            }

            $purchaseOrder = PurchaseOrder::updateOrCreate(
                ['id' => $request->input('id')],
                [
                    'po_number' => $request->input('po_number'),
                    'supplier_id' => $request->input('supplier_id'),
                    'po_date' => $request->input('po_date'),
                    'expected_date' => $request->filled('expected_date') ? $request->input('expected_date') : null,
                    'notes' => $request->input('notes'),
                    'status' => 'pending',
                    'created_by' => $request->input('id') ? Auth::id() : Auth::id(),
                    'updated_by' => $request->input('id') ? Auth::id() : null,
                ]
            );

            DB::commit();

            return response()->json([
                'message' => $request->input('id') ? 'Purchase order updated successfully' : 'Purchase order created successfully',
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        try {
            $purchaseOrder->delete();
            return response()->json('Purchase order deleted successfully', 200);
        } catch (\Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        try {
            $purchaseOrder->load(['items.product', 'supplier']);
            
            return inertia('PurchaseOrder/Show', [
                'purchase_order' => [
                    'id' => $purchaseOrder->id,
                    'po_number' => $purchaseOrder->po_number,
                    'po_date' => $purchaseOrder->po_date,
                    'status' => $purchaseOrder->status,
                    'total_amount' => $purchaseOrder->total_amount,
                    'notes' => $purchaseOrder->notes,
                    'supplier' => $purchaseOrder->supplier,
                    'items' => $purchaseOrder->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product' => $item->product,
                            'quantity' => $item->quantity,
                            'uom' => $item->uom,
                            'unit_cost' => $item->unit_cost,
                            'total_cost' => $item->total_cost
                        ];
                    })
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function packingList($id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'supplier',
            'packingLists.purchaseOrderItems.product',
            'receivedGoodsNotes.receiver',
            'receivedGoodsNotes.warehouse',
            'receivedGoodsNotes.packingList.purchaseOrderItems.product',
            'receivedGoodsNotes.packingList.purchaseOrderItems.warehouse',
            'po_items' => function ($q) {
                $q->where('quantity', '>', 0)
                    ->select('po_items.*')
                    ->selectRaw('COALESCE(MIN(products.name), po_items.item_description) as product_name')
                    ->leftJoin('products', function ($join) {
                        $join->on(function ($query) {
                            $query->where(function($q) {
                                $q->whereColumn('products.name', 'po_items.item_description')
                                  ->orWhereColumn('products.barcode', 'po_items.item_code');
                            });
                        });
                    })
                    ->groupBy([
                        'po_items.id',
                        'po_items.purchase_order_id',
                        'po_items.item_code',
                        'po_items.item_description',
                        'po_items.uom',
                        'po_items.quantity',
                        'po_items.original_quantity',
                        // 'po_items.received_quantity',
                        'po_items.unit_cost',
                        'po_items.total_cost',
                        'po_items.created_at',
                        'po_items.updated_at',
                        'po_items.deleted_at'
                    ]);
            }
        ])
            ->findOrFail($id);

        // Transform PO items while preserving original data
        $purchaseOrder['po_items'] = $purchaseOrder->po_items->map(function ($item) use ($purchaseOrder) {
            $originalData = $item->toArray();
            return array_merge($originalData, [
                'packing_list_id' => null,
                'damage_quantity' => 0,
                'warehouse_id' => null,
                'location' => '',
                'expiry_date' => null,
                'batch_number' => null,
                'generic_name' => null,
                'product_name' => $item->product_name ?? $item->item_description,
                'received_quantity' => 0
            ]);
        })->values();

        // Get packing list items
        $flattenedItems = collect();
        foreach ($purchaseOrder->packingLists as $packingList) {
            $items = collect($packingList->purchaseOrderItems)->map(function ($item) use ($packingList) {
                return array_merge($item->toArray(), [
                    'packing_list_number' => $packingList->packing_list_number,
                    'packing_list_date' => $packingList->created_at,
                    'packing_list_status' => $packingList->status,
                    'created_by' => $packingList->creator->name
                ]);
            });
            $flattenedItems = $flattenedItems->concat($items);
        }

        // Get all purchase order item IDs from the received goods notes
        $poItemIds = collect($purchaseOrder->receivedGoodsNotes)
            ->pluck('packing_list.purchase_order_items')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        // Load approvals separately
        $purchaseOrder['approvals'] = Approval::where('model', 'PurchaseOrderItem')
            ->whereIn('action', ['verify', 'approve'])
            ->with('role')
            ->get()
            ->values()
            ->toArray();

        return inertia('PurchaseOrder/Packing', [
            'purchase_order' => $purchaseOrder,
            'packingLists' => $flattenedItems->values()->all(),
            'warehouses' => Warehouse::get(),
            'purchase_orders' => PurchaseOrder::with(['po_items', 'items', 'packingLists'])->get()
        ]);
    }

    public function createBackOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        try {
            $request->validate([
                'items' => 'required|array',
                'items.*.id' => 'nullable|exists:back_orders,id',
                'items.*.purchase_order_id' => 'nullable|exists:purchase_orders,id',
                'items.*.type' => 'required|string',
                'items.*.quantity' => 'required|integer|min:0',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.notes' => 'nullable|string'
            ]);

            foreach ($request->items as $item) {
                // Find existing back order
                $backOrder = BackOrder::where([
                    'product_id' => $item['product_id'],
                    'purchase_order_id' => $item['purchase_order_id'],
                    'type' => $item['type'],
                ])->first();

                if ($item['quantity'] == 0) {
                    // Delete if exists and quantity is 0
                    if ($backOrder) {
                        $backOrder->delete();
                    }
                } else {
                    // Update or create with new quantity
                    if ($backOrder) {
                        $backOrder->update([
                            'quantity' => $item['quantity'],
                            'notes' => $item['notes'] ?? null,
                        ]);
                    } else {
                        BackOrder::create([
                            'product_id' => $item['product_id'],
                            'purchase_order_id' => $item['purchase_order_id'],
                            'type' => $item['type'],
                            'quantity' => $item['quantity'],
                            'notes' => $item['notes'] ?? null,
                            'created_by' => auth()->id(),
                            'status' => 'pending'
                        ]);
                    }
                }
            }

            return response()->json('Back orders updated successfully', 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function packingListStore(Request $request, PurchaseOrder $purchaseOrder)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'packing_list_id' => 'nullable|exists:packing_lists,id',
                'purchase_order_id' => 'required|exists:purchase_orders,id',
                'items' => 'required|array',
                'items.*.quantity' => 'nullable|numeric|min:0',
                'items.*.received_quantity' => 'nullable|numeric|min:0',
                'items.*.warehouse_id' => 'nullable|exists:warehouses,id',
                'items.*.product_name' => 'required',
                'items.*.generic_name' => 'nullable|string',
                'items.*.location' => 'nullable|string',
                'items.*.batch_number' => 'nullable|string',
                'items.*.expiry_date' => 'nullable|date',
                'items.*.unit_cost' => 'nullable|numeric|min:0',
                'items.*.total_cost' => 'nullable|numeric|min:0',
                'items.*.damage_quantity' => 'nullable|numeric|min:0',
            ]);

            foreach ($request->items as $item) {
                // Find or create PurchaseOrderItem
                $product = Product::where('name', $item['product_name'])->first();
                $item['product_id'] = $product->id;
                $purchaseOrderItem = PurchaseOrderItem::create([
                    'packing_list_id' => $validated['packing_list_id'],
                    'purchase_order_id' => $validated['purchase_order_id'],
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'location' => $item['location'],
                    'batch_number' => $item['batch_number'],
                    'expiry_date' => $item['expiry_date'],
                    'generic_name' => $item['product_name'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'received_quantity' => $item['received_quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $item['total_cost'],
                    'damage_quantity' => $item['damage_quantity'],
                ]);

                            // Find and update corresponding PurchaseOrderItem
            $poItem = PurchaseOrderItem::where('item_description', $item['product_name'])
                    ->where('purchase_order_id', $validated['purchase_order_id'])
                    ->first();

                if ($poItem) {
                    // Update po_items quantity: original quantity - received quantity
                    $poItem->quantity = $poItem->quantity - $item['received_quantity'] - $item['damage_quantity'];
                    $poItem->save();
                }

                if ($item['damage_quantity'] > 0) {
                    BackOrder::updateOrCreate([
                        'purchase_order_id' => $validated['purchase_order_id'],
                        'product_id' => $item['product_id'],
                    ], [
                        'quantity' => $item['damage_quantity'],
                        'type' => 'damaged',
                        'created_by' => Auth::id(),
                        'status' => 'pending'
                    ]);
                }
            }

            DB::commit();
            return response()->json('Items updated successfully', 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function importItems(Request $request)
    {
        try {
            $request->validate([
                'file' => [
                    'required',
                    'file',
                    'mimes:xlsx,xls'
                ],
                'purchase_order_id' => 'required|exists:purchase_orders,id'
            ], [
                'file.mimes' => 'The file must be an Excel file (xlsx or xls)',
            ]);
            
            // Process the import directly
            $import = new PurchaseOrderItemsImport($request->purchase_order_id);
            Excel::import($import, $request->file('file'));

            return redirect()->route('supplies.editPO', $request->purchase_order_id)->with('success', 'Purchase order items imported successfully');

        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function getImportProgress(Request $request)
    {
        try {
            $importId = $request->input('import_id');
            
            if (!$importId) {
                return response()->json(['error' => 'Import ID is required'], 400);
            }

            $progress = Cache::get($importId, 0);
            
            return response()->json([
                'import_id' => $importId,
                'progress' => $progress,
                'status' => 'completed' // Since import is now synchronous
            ]);

        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getBackOrders(PurchaseOrder $purchaseOrder, $productId)
    {
        $backOrders = BackOrder::where('purchase_order_id', $purchaseOrder->id)
            ->where('product_id', $productId)
            ->get();

        return response()->json($backOrders);
    }

    public function generatePackingList(Request $request)
    {
        try {
            $request->validate([
                'purchase_order_id' => 'required|exists:purchase_orders,id',
            ]);

            // Get today's date in YYYYMMDD format
            $today = now()->format('Ymd');

            // Find the last packing list number for today
            $lastPackingList = PackingList::where('packing_list_number', 'like', "PL-{$today}-%")
                ->orderBy('packing_list_number', 'desc')
                ->first();

            // Extract sequence number and increment
            $sequence = 1;
            if ($lastPackingList) {
                $parts = explode('-', $lastPackingList->packing_list_number);
                $sequence = intval(end($parts)) + 1;
            }

            // Generate new packing list number with sequence
            $timestamp = now()->format('His');
            $packingListNumber = sprintf(
                "PL-%s-%s-%s-%03d",
                $today,
                str_pad($request->purchase_order_id, 3, '0', STR_PAD_LEFT),
                $timestamp,
                $sequence
            );

            $packingList = PackingList::create([
                'purchase_order_id' => $request->purchase_order_id,
                'created_by' => Auth::id(),
                'packing_list_number' => $packingListNumber,
                'packing_date' => Carbon::now()->toDateString(),
                'status' => 'pending'
            ]);

            return response()->json([
                'message' => 'Packing list generated successfully',
                'packing_list' => $packingList
            ], 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function getPackingListItems($id)
    {
        try {
            $items = PurchaseOrderItem::where('packing_list_id', $id)
                ->with('product:id,name')
                ->get()
                ->map(function ($item) {
                    $item['product_name'] = $item->product->name;
                    $item['product_id'] = $item->product->id;
                    return $item;
                });
            return response()->json(['items' => $items]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createPackingList(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $roleIds = $user->roles->pluck('id')->toArray();
            $approvals = Approval::where('model', 'PurchaseOrderItem')
                ->whereIn('role_id', $roleIds)
                ->whereIn('action', ['confirm', 'verify', 'approve'])
                ->get();

            $canConfirm = $approvals->where('action', 'confirm')->count() > 0;
            $canVerify = $approvals->where('action', 'verify')->count() > 0;
            $canApprove = $approvals->where('action', 'approve')->count() > 0;

            if (!$canConfirm || !$canVerify || !$canApprove) {
                return response()->json('Unauthorized to perform this action', 401);
            }
            // Generate a unique packing list number
            $packingListNumber = 'PL-' . now()->format('Ymd') . '-' . rand(1000, 9999);

            // Create the packing list
            $packingList = PackingList::create([
                'purchase_order_id' => $id,
                'created_by' => Auth::id(),
                'packing_list_number' => $packingListNumber,
                'packing_date' => Carbon::now()->toDateString(),
            ]);

            // Create a Received Goods Note
            $rgnNumber = 'GRN-' . now()->format('Ymd') . '-' . rand(1000, 9999);
            if ($user->warehouse_id == null) {
                return response()->json('You need to assign a warehouse to yourself before creating a packing list.', 401);
            }
            ReceivedGoodsNote::create([
                'packing_list_id' => $packingList->id,
                'rgn_number' => $rgnNumber,
                'receiver_id' => $user->id,
                'warehouse_id' => $user->warehouse_id,
                'received_at' => Carbon::now(),
                'status' => 'pending'
            ]);

            return response()->json([
                'message' => 'Packing list created successfully',
                'packing_list' => $packingList
            ]);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function bulkApprove(Request $request, $purchaseOrder)
    {
        try {
            $request->validate([
                'items' => 'required|array',
                'items.*' => 'exists:purchase_order_items,id',
                'status' => 'required|in:verified,approved'
            ]);

            $items = DB::table('purchase_order_items')
                ->whereIn('id', $request->items)
                ->where('purchase_order_id', $purchaseOrder)
                ->get();

            // if ($request->status === 'approved') {
            //     // Check if all items are verified first
            //     foreach ($items as $item) {
            //         if ($item->status !== 'verified') {
            //             return response()->json('All items must be verified first', 500);
            //         }
            //     }
            // }

            foreach ($items as $item) {
                $updateData = [
                    'status' => $request->status
                ];

                if ($request->status === 'verified') {
                    $updateData['verified_at'] = now();
                    $updateData['verified_by'] = auth()->id();
                } else {
                    $updateData['approved_at'] = now();
                    $updateData['approved_by'] = auth()->id();
                }

                DB::table('purchase_order_items')
                    ->where('id', $item->id)
                    ->update($updateData);

                // If approving, update inventory
                if ($request->status === 'approved') {
                    $inventory = DB::table('inventories')
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $item->warehouse_id)
                        ->first();

                    if ($inventory) {
                        DB::table('inventories')
                            ->where('id', $inventory->id)
                            ->update([
                                'quantity' => DB::raw('quantity + ' . $item->received_quantity),
                                'batch_number' => $item->batch_number,
                                'expiry_date' => $item->expiry_date,
                                'unit_cost' => $item->unit_cost,
                                'updated_at' => now()
                            ]);
                    } else {
                        DB::table('inventories')->insert([
                            'product_id' => $item->product_id,
                            'warehouse_id' => $item->warehouse_id,
                            'quantity' => $item->received_quantity,
                            'batch_number' => $item->batch_number,
                            'expiry_date' => $item->expiry_date,
                            'unit_cost' => $item->unit_cost,
                            'location' => $item->location,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }

            // Check if all items for this purchase order have been approved and have quantity = 0
            if ($request->status === 'approved') {
                // Get the purchase order with its items
                PurchaseOrder::find($purchaseOrder)
                    ->whereHas('po_items', function ($query) {
                        $query->where('quantity', 0);
                    })
                    ->update(['status' => 'completed']);
            }

            return response()->json(['message' => 'Items ' . $request->status . ' successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateItem(Request $request, $purchaseOrder)
    {
        try {
            $request->validate([
                'received_quantity' => 'required|integer|min:0',
                'damage_quantity' => 'nullable|integer|min:0',
                'batch_number' => 'nullable|string',
                'expiry_date' => 'nullable|date',
                'location' => 'nullable|string'
            ]);

            DB::table('purchase_order_items')
                ->where('id', $request->id)
                ->where('purchase_order_id', $purchaseOrder)
                ->update([
                    'received_quantity' => $request->received_quantity,
                    'damage_quantity' => $request->damage_quantity,
                    'batch_number' => $request->batch_number,
                    'expiry_date' => $request->expiry_date,
                    'location' => $request->location,
                    'updated_at' => now()
                ]);

            return response()->json(['message' => 'Item updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function verifyItem(Request $request, $purchaseOrder)
    {
        try {
            $request->validate([
                'id' => 'required|exists:purchase_order_items,id'
            ]);

            DB::table('purchase_order_items')
                ->where('id', $request->id)
                ->where('purchase_order_id', $purchaseOrder)
                ->update([
                    'status' => 'verified',
                    'verified_at' => now(),
                    'verified_by' => auth()->id()
                ]);

            return response()->json(['message' => 'Item verified successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function approveItem(Request $request, $purchaseOrder)
    {
        try {
            $request->validate([
                'id' => 'required|exists:purchase_order_items,id'
            ]);

            $item = DB::table('purchase_order_items')
                ->where('id', $request->id)
                ->where('purchase_order_id', $purchaseOrder)
                ->where('status', 'verified')
                ->first();

            if (!$item) {
                return response()->json('Item must be verified first', 500);
            }

            DB::table('purchase_order_items')
                ->where('id', $request->id)
                ->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => auth()->id()
                ]);

            // Update inventory
            $inventory = DB::table('inventories')
                ->where('product_id', $item->product_id)
                ->where('warehouse_id', $item->warehouse_id)
                ->first();

            if ($inventory) {
                DB::table('inventories')
                    ->where('id', $inventory->id)
                    ->update([
                        'quantity' => DB::raw('quantity + ' . $item->received_quantity),
                        'batch_number' => $item->batch_number,
                        'expiry_date' => $item->expiry_date,
                        'unit_cost' => $item->unit_cost,
                        'updated_at' => now()
                    ]);
            } else {
                DB::table('inventories')->insert([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'quantity' => $item->received_quantity,
                    'batch_number' => $item->batch_number,
                    'expiry_date' => $item->expiry_date,
                    'unit_cost' => $item->unit_cost,
                    'location' => $item->location,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json(['message' => 'Item approved and inventory updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteItems(Request $request)
    {
        try {
            $request->validate([
                'items' => 'required|array',
                'purchase_order_id' => 'required|exists:purchase_orders,id'
            ]);

            $canDelete = [];
            $cannotDelete = [];

            // Check each item if it can be deleted
            foreach ($request->items as $itemId) {
                $item = PurchaseOrderItem::where('id', $itemId)
                    ->where('purchase_order_id', $request->purchase_order_id)
                    ->first();

                if ($item) {
                    // Check if quantity equals original_quantity
                    if ($item->quantity == $item->original_quantity) {
                        $canDelete[] = $itemId;
                    } else {
                        $cannotDelete[] = $itemId;
                    }
                }
            }

            // Delete items that can be deleted
            if (count($canDelete) > 0) {
                PurchaseOrderItem::whereIn('id', $canDelete)
                    ->where('purchase_order_id', $request->purchase_order_id)
                    ->delete();
            }

            // Return appropriate response
            if (count($cannotDelete) > 0) {
                if (count($canDelete) > 0) {
                    return response()->json("Deleted " . count($canDelete) . " items. Could not delete " . count($cannotDelete) . " items because quantities have been modified.", 207);
                } else {
                    return response()->json("Cannot delete items because quantities have been modified.", 500);
                }
            }

            return response()->json("Successfully deleted " . count($canDelete) . " items", 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
}
