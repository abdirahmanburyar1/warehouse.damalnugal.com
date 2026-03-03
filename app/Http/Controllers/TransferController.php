<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Models\Facility;
use App\Models\FacilityInventory;
use App\Models\FacilityInventoryItem;
use App\Models\FacilityBackorder;
use App\Models\Reason;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Product;
use App\Models\IssuedQuantity;
use App\Models\Disposal;
use App\Models\BackOrderHistory;
use App\Models\Region;
use App\Models\BackOrder;
use App\Models\InventoryItem;
use App\Models\InventoryAllocation;
use App\Models\PackingListDifference;
use App\Models\Liquidate;
use App\Models\ReceivedQuantity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TransferCreated;
use App\Events\TransferStatusChanged;
use App\Events\InventoryUpdated;
use App\Events\FacilityInventoryUpdated;
use App\Events\FacilityInventoryTestEvent;
use App\Models\Driver;
use App\Models\LogisticCompany;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\TransferResource;
use App\Notifications\TransferActionRequired;

class TransferController extends Controller
{

    public function changeItemStatus(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'transfer_id' => 'required|exists:transfers,id',
                'status' => 'required|in:reviewed,approved,rejected,in_process,dispatched,delivered,received'
            ]);

            $transfer = Transfer::find($request->transfer_id);
            if(!$transfer){
                return response()->json("Not Found or you are not authorized to take this action", 500);
            }
            
            // Determine user's role in the transfer
            $currentUser = auth()->user();
            $currentWarehouse = $currentUser->warehouse;
            $currentFacility = $currentUser->facility_id;
            
            // User is sender if their warehouse/facility is the source
            $isSender = ($transfer->from_warehouse_id === $currentWarehouse?->id) || 
                       ($transfer->from_facility_id === $currentFacility);
            
            // User is receiver if their warehouse/facility is the destination
            $isReceiver = ($transfer->to_warehouse_id === $currentWarehouse?->id) || 
                         ($transfer->to_facility_id === $currentFacility);
            
            // Store the old status before making any changes
            $oldStatus = $transfer->status;
            $newStatus = $request->status;

            // pending -> reviewed (SENDER ACTION)
            if($oldStatus == 'pending' && $newStatus == 'reviewed' && $isSender && auth()->user()->can('transfer.review')){                
                $transfer->update([
                    'status' => 'reviewed',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now()
                ]);
                
                // Dispatch event for status change
                //// event(new TransferStatusChanged($transfer, $oldStatus, $newStatus, auth()->id()));
            }
            
            // pending -> rejected (branch) (SENDER ACTION)
            if($oldStatus == 'pending' && $newStatus == 'rejected' && $isSender && auth()->user()->can('transfer.approve')){
                $transfer->update([
                    'status' => 'rejected',
                    'rejected_by' => auth()->id(),
                    'rejected_at' => now()
                ]);
                
                // Dispatch event for status change
                //// event(new TransferStatusChanged($transfer, $oldStatus, $newStatus, auth()->id()));
            }
            
            // reviewed -> approved (SENDER ACTION)
            if($oldStatus == 'reviewed' && $newStatus == 'approved' && $isSender && auth()->user()->can('transfer.approve')){                
                $transfer->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now()
                ]);
                
                // Dispatch event for status change
                //// event(new TransferStatusChanged($transfer, $oldStatus, $newStatus, auth()->id()));
            }
            
            // reviewed -> rejected (branch) (SENDER ACTION)
            if($oldStatus == 'reviewed' && $newStatus == 'rejected' && $isSender && auth()->user()->can('transfer.approve')){
                $transfer->update([
                    'status' => 'rejected',
                    'rejected_by' => auth()->id(),
                    'rejected_at' => now()
                ]);
                
                // Dispatch event for status change
                //// event(new TransferStatusChanged($transfer, $oldStatus, $newStatus, auth()->id()));
            }
            
            // approved -> in_process (SENDER ACTION)
            if($oldStatus == 'approved' && $newStatus == 'in_process' && $isSender && auth()->user()->can('transfer.in_process')){
                $transfer->update([
                    'status' => 'in_process',
                    'processed_by' => auth()->id(),
                    'processed_at' => now()
                ]);
                
                // Dispatch event for status change
                //// event(new TransferStatusChanged($transfer, $oldStatus, $newStatus, auth()->id()));
            }

            // in_process -> dispatched (SENDER ACTION)
            if($oldStatus == 'in_process' && $newStatus == 'dispatched' && $isSender && auth()->user()->can('transfer.dispatch')){
                $transfer->update([
                    'status' => 'dispatched',
                    'dispatched_by' => auth()->id(),    
                    'dispatched_at' => now()
                ]);
                
                // Dispatch event for status change
                //// event(new TransferStatusChanged($transfer, $oldStatus, $newStatus, auth()->id()));
            }
            
            // dispatched -> delivered (RECEIVER ACTION)
            if($oldStatus == 'dispatched' && $newStatus == 'delivered' && $isReceiver && auth()->user()->can('transfer.deliver')){
                $transfer->update([
                    'status' => 'delivered',
                    'delivered_by' => auth()->id(),    
                    'delivered_at' => now()
                ]);
                
                // Dispatch event for status change
                //// event(new TransferStatusChanged($transfer, $oldStatus, $newStatus, auth()->id()));
            }
            
            // delivered -> received (RECEIVER ACTION): only the "to" warehouse/facility can receive; at least one allocation must have received_quantity > 0
            if($oldStatus == 'delivered' && $newStatus == 'received'){
                if (!$isReceiver) {
                    DB::rollBack();
                    return response()->json('You are not authorized to receive this transfer. Only the destination warehouse or facility can receive.', 403);
                }
                if (!auth()->user()->can('transfer.receive') && !auth()->user()->can('transfer.manage')) {
                    DB::rollBack();
                    return response()->json('You do not have permission to receive transfers.', 403);
                }
                $hasReceivedQty = $transfer->items()
                    ->whereHas('inventory_allocations', fn ($q) => $q->where('received_quantity', '>', 0))
                    ->exists();
                if (!$hasReceivedQty) {
                    DB::rollBack();
                    return response()->json('Enter received quantity for at least one item before marking the transfer as received.', 422);
                }
                $transfer->update([
                    'status' => 'received',
                    'received_by' => auth()->id(),
                    'received_at' => now()
                ]);
            }
            
            DB::commit();
            
            // Return debug information along with success message
            return response()->json("Transfer status changed successfully", 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function index(Request $request)
    {
        // Start building the query
        $query = Transfer::query();
        
        $query->with('fromWarehouse', 'toWarehouse', 'fromFacility','fromFacility', 'fromWarehouse', 'toFacility', 'items')
            ->withCount('items')
            ->latest('created_at');
        
        // Get current user context
        $currentUser = auth()->user();
        $currentWarehouse = $currentUser->warehouse;
        $currentFacility = $currentUser->facility_id;
        
        // Apply filters
        // Filter by transfer direction (top level tab)
        if ($request->filled('direction_tab')) {
            if ($request->direction_tab === 'in') {
                // In Transfers: where user's warehouse is the destination
                $query->where('to_warehouse_id', $currentWarehouse?->id);
            } elseif ($request->direction_tab === 'out') {
                // Out Transfers: where user's warehouse is the source
                $query->where('from_warehouse_id', $currentWarehouse?->id);
            } elseif ($request->direction_tab === 'other') {
                // Other Transfers: where user's warehouse is neither source nor destination
                $query->where('from_warehouse_id', '!=', $currentWarehouse?->id)
                      ->where('to_warehouse_id', '!=', $currentWarehouse?->id);
            }
        }
        
        // Filter by status (second level tab)
        if ($request->filled('tab') && $request->tab !== 'all') {
            $query->where('status', $request->tab);
        }
        
        // Filter by search term
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('transferID', 'like', $searchTerm)
                  ->orWhereHas('fromFacility', function($q) use ($searchTerm) {
                      $q->where('name', 'like', $searchTerm);
                  })
                  ->orWhereHas('toFacility', function($q) use ($searchTerm) {
                      $q->where('name', 'like', $searchTerm);
                  })
                  ->orWhereHas('fromWarehouse', function($q) use ($searchTerm) {
                      $q->where('name', 'like', $searchTerm);
                  })
                  ->orWhereHas('toWarehouse', function($q) use ($searchTerm) {
                      $q->where('name', 'like', $searchTerm);
                  });
            });
        }
        
        if ($request->filled('transfer_type')) {
            switch ($request->transfer_type) {
                case 'Warehouse to Warehouse':
                    $query->whereNotNull('from_warehouse_id')
                          ->whereNotNull('to_warehouse_id');
                    break;
        
                case 'Facility to Facility':
                    $query->whereNotNull('from_facility_id')
                          ->whereNotNull('to_facility_id');
                    break;
        
                case 'Facility to Warehouse':
                    $query->whereNotNull('from_facility_id')
                          ->whereNotNull('to_warehouse_id');
                    break;
        
                case 'Warehouse to Facility':
                    $query->whereNotNull('from_warehouse_id')
                          ->whereNotNull('to_facility_id');
                    break;
            }
        }
        

       



        if ($request->filled('date_from') && !$request->filled('date_to')) {
            $dateFrom = Carbon::parse($request->date_from)->format('Y-m-d');
            $query->whereDate('transfer_date', $dateFrom);
        
        } elseif ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = Carbon::parse($request->date_from)->format('Y-m-d');
            $dateTo = Carbon::parse($request->date_to)->format('Y-m-d');
        
            $query->whereBetween('transfer_date', [$dateFrom, $dateTo]);
        }

        // Region filtering - more targeted approach
        if ($request->filled('region')) {
            $query->where(function($q) use ($request) {
                $q->whereHas('fromWarehouse', function($subQ) use ($request) {
                    $subQ->where('region', $request->region);
                })->orWhereHas('toWarehouse', function($subQ) use ($request) {
                    $subQ->where('region', $request->region);
                })->orWhereHas('fromFacility', function($subQ) use ($request) {
                    $subQ->where('region', $request->region);
                })->orWhereHas('toFacility', function($subQ) use ($request) {
                    $subQ->where('region', $request->region);
                });
            });
        }

        // District filtering - more targeted approach
        if ($request->filled('district')) {
            $query->where(function($q) use ($request) {
                $q->whereHas('fromWarehouse', function($subQ) use ($request) {
                    $subQ->where('district', $request->district);
                })->orWhereHas('toWarehouse', function($subQ) use ($request) {
                    $subQ->where('district', $request->district);
                })->orWhereHas('fromFacility', function($subQ) use ($request) {
                    $subQ->where('district', $request->district);
                })->orWhereHas('toFacility', function($subQ) use ($request) {
                    $subQ->where('district', $request->district);
                });
            });
        }

        // Specific source/destination filtering (using warehouse/facility names)
        if ($request->filled('from_warehouse')) {
            $warehouse = Warehouse::where('name', $request->from_warehouse)->first();
            if ($warehouse) {
                $query->where('from_warehouse_id', $warehouse->id);
            }
        }
        
        if ($request->filled('to_warehouse')) {
            $warehouse = Warehouse::where('name', $request->to_warehouse)->first();
            if ($warehouse) {
                $query->where('to_warehouse_id', $warehouse->id);
            }
        }
        
        if ($request->filled('from_facility')) {
            $facility = Facility::where('name', $request->from_facility)->first();
            if ($facility) {
                $query->where('from_facility_id', $facility->id);
            }
        }
        
        if ($request->filled('to_facility')) {
            $facility = Facility::where('name', $request->to_facility)->first();
            if ($facility) {
                $query->where('to_facility_id', $facility->id);
            }
        }

        // General facility/warehouse filtering (used when no specific transfer type is selected)
        if ($request->filled('facility')) {
            $facility = Facility::where('name', $request->facility)->first();
            if ($facility) {
                $query->where(function($q) use ($facility) {
                    $q->where('from_facility_id', $facility->id)
                      ->orWhere('to_facility_id', $facility->id);
                });
            }
        }

        if ($request->filled('warehouse')) {
            $warehouse = Warehouse::where('name', $request->warehouse)->first();
            if ($warehouse) {
                $query->where(function($q) use ($warehouse) {
                    $q->where('from_warehouse_id', $warehouse->id)
                      ->orWhere('to_warehouse_id', $warehouse->id);
                });
            }
        }


        
        // Execute the query
        $transfers = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
        ->withQueryString();
        $transfers->setPath(url()->current()); // Force Laravel to use full URLs

        logger()->info($transfers);
        
        // Get context-aware statistics based on user's warehouse and direction tab
        $statisticsQuery = Transfer::query();
        
        // Apply same direction filtering to statistics as main query
        if ($request->filled('direction_tab')) {
            if ($request->direction_tab === 'in') {
                $statisticsQuery->where('to_warehouse_id', $currentWarehouse?->id);
            } elseif ($request->direction_tab === 'out') {
                $statisticsQuery->where('from_warehouse_id', $currentWarehouse?->id);
            } elseif ($request->direction_tab === 'other') {
                $statisticsQuery->where('from_warehouse_id', '!=', $currentWarehouse?->id)
                               ->where('to_warehouse_id', '!=', $currentWarehouse?->id);
            }
        }
        
        // Apply region/district filtering to statistics if present
        if ($request->filled('region')) {
            $statisticsQuery->where(function($q) use ($request) {
                $q->whereHas('fromWarehouse', function($subQ) use ($request) {
                    $subQ->where('region', $request->region);
                })->orWhereHas('toWarehouse', function($subQ) use ($request) {
                    $subQ->where('region', $request->region);
                })->orWhereHas('fromFacility', function($subQ) use ($request) {
                    $subQ->where('region', $request->region);
                })->orWhereHas('toFacility', function($subQ) use ($request) {
                    $subQ->where('region', $request->region);
                });
            });
        }
        
        if ($request->filled('district')) {
            $statisticsQuery->where(function($q) use ($request) {
                $q->whereHas('fromWarehouse', function($subQ) use ($request) {
                    $subQ->where('district', $request->district);
                })->orWhereHas('toWarehouse', function($subQ) use ($request) {
                    $subQ->where('district', $request->district);
                })->orWhereHas('fromFacility', function($subQ) use ($request) {
                    $subQ->where('district', $request->district);
                })->orWhereHas('toFacility', function($subQ) use ($request) {
                    $subQ->where('district', $request->district);
                });
            });
        }
        
        // Apply transfer type filtering to statistics
        if ($request->filled('transfer_type')) {
            switch ($request->transfer_type) {
                case 'Warehouse to Warehouse':
                    $statisticsQuery->whereNotNull('from_warehouse_id')
                          ->whereNotNull('to_warehouse_id');
                    break;
        
                case 'Facility to Facility':
                    $statisticsQuery->whereNotNull('from_facility_id')
                          ->whereNotNull('to_facility_id');
                    break;
        
                case 'Facility to Warehouse':
                    $statisticsQuery->whereNotNull('from_facility_id')
                          ->whereNotNull('to_warehouse_id');
                    break;
        
                case 'Warehouse to Facility':
                    $statisticsQuery->whereNotNull('from_warehouse_id')
                          ->whereNotNull('to_facility_id');
                    break;
            }
        }

        // Apply general facility/warehouse filtering to statistics
        if ($request->filled('facility')) {
            $facility = Facility::where('name', $request->facility)->first();
            if ($facility) {
                $statisticsQuery->where(function($q) use ($facility) {
                    $q->where('from_facility_id', $facility->id)
                      ->orWhere('to_facility_id', $facility->id);
                });
            }
        }

        if ($request->filled('warehouse')) {
            $warehouse = Warehouse::where('name', $request->warehouse)->first();
            if ($warehouse) {
                $statisticsQuery->where(function($q) use ($warehouse) {
                    $q->where('from_warehouse_id', $warehouse->id)
                      ->orWhere('to_warehouse_id', $warehouse->id);
                });
            }
        }
        
        $allTransfers = $statisticsQuery->get();
        $total = $allTransfers->count();
        $approvedCount = $allTransfers->whereIn('status', ['approved'])->count();
        $reviewedCount = $allTransfers->whereIn('status', ['reviewed'])->count();
        $inProcessCount = $allTransfers->whereIn('status', ['in_process'])->count();
        $dispatchedCount = $allTransfers->where('status', 'dispatched')->count();
        $receivedCount = $allTransfers->where('status', 'received')->count();
        $rejectedCount = $allTransfers->where('status', 'rejected')->count();
        $pendingCount = $allTransfers->where('status', 'pending')->count();
        
        $statistics = [
            'approved' => [
                'count' => $approvedCount,
                'percentage' => $total > 0 ? round(($approvedCount / $total) * 100) : 0,
                'stages' => ['approved']
            ],
            'pending' => [
                'count' => $pendingCount,
                'percentage' => $total > 0 ? round(($pendingCount / $total) * 100) : 0,
                'stages' => ['pending']
            ],
            'reviewed' => [
                'count' => $reviewedCount,
                'percentage' => $total > 0 ? round(($reviewedCount / $total) * 100) : 0,
                'stages' => ['reviewed']
            ],
            'in_process' => [
                'count' => $inProcessCount,
                'percentage' => $total > 0 ? round(($inProcessCount / $total) * 100) : 0,
                'stages' => ['in_process']
            ],
            'dispatched' => [
                'count' => $dispatchedCount,
                'percentage' => $total > 0 ? round(($dispatchedCount / $total) * 100) : 0,
                'stages' => ['dispatched']
            ],
            'received' => [
                'count' => $receivedCount,
                'percentage' => $total > 0 ? round(($receivedCount / $total) * 100) : 0,
                'stages' => ['received']
            ],
            'rejected' => [
                'count' => $rejectedCount,
                'percentage' => $total > 0 ? round(($rejectedCount / $total) * 100) : 0,
                'stages' => ['rejected']
            ]
        ];
        
        // Get data for filter dropdowns
        $locations = DB::table('locations')->select('id', 'location')->orderBy('location')->get();

        return inertia('Transfer/Index', [
            'transfers' => TransferResource::collection($transfers),
            'statistics' => $statistics,
            'locations' => $locations,
            'filters' => $request->only([
                'search', 
                'facility', 
                'warehouse', 
                'date_from', 
                'date_to', 
                'tab',
                'per_page',
                'page',
                'region',
                'district',
                'direction_tab',
                'transfer_type',
                'from_warehouse',
                'to_warehouse',
                'from_facility',
                'to_facility'
            ]),
            'regions' => Region::pluck('name')->toArray()
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasPermission('transfer-create') && !auth()->user()->isAdmin) {
            abort(403, 'You do not have permission to create transfers.');
        }
        DB::beginTransaction();
        try {
            $request->validate([
                'source_type' => 'required|in:warehouse,facility',
                'source_id' => 'required|integer',
                'destination_type' => 'required|in:warehouse,facility',
                'destination_id' => 'required|integer',
                'transfer_date' => 'required|date',
                'transferID' => 'required',
                'items' => 'required|array',
                // 'items.*.product_id' => 'required|integer',
                // 'items.*.quantity' => 'required|integer|min:1',
                // 'items.*.details' => 'required|array',
                // 'items.*.details.*.quantity_to_transfer' => 'required_if:items.*.details.*.transfer_reason,!=,null|integer',
                // 'items.*.details.*.id' => 'required|integer',
                // 'items.*.details.*.transfer_reason' => 'required_if:items.*.details.*.quantity_to_transfer,!=,0|string',
                // 'notes' => 'nullable|string',
                'transfer_type' => 'nullable|string'
            ]);
    
            // Determine transfer type based on source and destination types
            $sourceTypeFormatted = ucfirst($request->source_type);
            $destinationTypeFormatted = ucfirst($request->destination_type);
            $automaticTransferType = "{$sourceTypeFormatted} to {$destinationTypeFormatted}";

            $transferData = [
                'transferID' => $request->transferID,
                'transfer_date' => $request->transfer_date,
                'from_warehouse_id' => $request->source_type === 'warehouse' ? $request->source_id : null,
                'from_facility_id' => $request->source_type === 'facility' ? $request->source_id : null,
                'to_warehouse_id' => $request->destination_type === 'warehouse' ? $request->destination_id : null,
                'to_facility_id' => $request->destination_type === 'facility' ? $request->destination_id : null,
                'transfer_type' => $automaticTransferType,
                'created_by' => auth()->id(),
            ];
    
            $transfer = Transfer::create($transferData);
    
            foreach ($request->items as $item) {
                // Calculate total quantity on hand for this product from the source inventory only
                if ($request->source_type === 'warehouse') {
                    $totalQuantityOnHand = InventoryItem::where('product_id', $item['product_id'])
                        ->where('warehouse_id', $request->source_id)
                        ->where('quantity', '>', 0)
                        ->where('expiry_date', '>', \Carbon\Carbon::now())
                        ->sum('quantity');
                } else {
                    $totalQuantityOnHand = FacilityInventoryItem::where('product_id', $item['product_id'])
                        ->whereHas('inventory', function($query) use ($request) {
                            $query->where('facility_id', $request->source_id);
                        })
                        ->where('quantity', '>', 0)
                        ->where('expiry_date', '>', \Carbon\Carbon::now())
                        ->sum('quantity');
                }

                // Ensure totalQuantityOnHand is a valid number
                $totalQuantityOnHand = is_numeric($totalQuantityOnHand) ? (float) $totalQuantityOnHand : 0.0;

                // Create transfer item for this product
                $transferItem = $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'], // Total requested quantity
                    'quantity_to_release' => $item['quantity'],
                    'quantity_per_unit' => $totalQuantityOnHand // Save total quantity on hand at time of transfer creation
                ]);

                // Process each detail item with specific quantities to transfer
                foreach ($item['details'] as $detail) {
                    $quantityToTransfer = $detail['quantity_to_transfer'];
                    
                    if ($quantityToTransfer <= 0) continue;

                    // Find the specific inventory item by ID
                    if ($request->source_type === 'warehouse') {
                        $inventoryItem = InventoryItem::find($detail['id']);
                    } else {
                        $inventoryItem = FacilityInventoryItem::find($detail['id']);
                    }

                    if (!$inventoryItem) {
                        throw new \Exception("Inventory item with ID {$detail['id']} not found");
                    }

                    // Verify we have enough quantity
                    if ($quantityToTransfer > $inventoryItem->quantity) {
                        throw new \Exception("Insufficient quantity. Available: {$inventoryItem->quantity}, Requested: {$quantityToTransfer}");
                    }
                    
                    // Create inventory allocation record for detailed tracking
                    $transferItem->inventory_allocations()->create([
                        'product_id' => $item['product_id'],
                        'warehouse_id' => $request->source_type === 'warehouse' ? $request->source_id : null,
                        'location' => $inventoryItem->location,
                        'batch_number' => $inventoryItem->batch_number,
                        'expiry_date' => $inventoryItem->expiry_date,
                        'allocated_quantity' => $quantityToTransfer,
                        'uom' => $inventoryItem->uom,
                        'barcode' => $inventoryItem->barcode,
                        'allocation_type' => 'transfer',
                        'unit_cost' => $inventoryItem->unit_cost ?? 0,
                        'total_cost' => $quantityToTransfer * ($inventoryItem->unit_cost ?? 0),
                        'transfer_reason' => $detail['transfer_reason'],
                    ]);

                    // Deduct from source inventory
                    $inventoryItem->quantity -= $quantityToTransfer;
                    $inventoryItem->save();
                }
        
            }
    
            $transfer->load(['fromWarehouse', 'toWarehouse', 'fromFacility', 'toFacility', 'items.product']);
    
            if ($transfer->to_warehouse_id && $transfer->toWarehouse?->manager_email) {
                Notification::route('mail', $transfer->toWarehouse->manager_email)
                    ->notify(new TransferCreated($transfer));
            } elseif ($transfer->to_facility_id && $transfer->toFacility?->email) {
                Notification::route('mail', $transfer->toFacility->email)
                    ->notify(new TransferCreated($transfer));
            }

            // Workflow: notify users with transfer-review permission (next action = review)
            $reviewers = User::withPermission('transfer-review')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            foreach ($reviewers as $user) {
                $user->notify(new TransferActionRequired($transfer, TransferActionRequired::ACTION_NEEDS_REVIEW));
            }
    
            DB::commit();
            return response()->json('Transfer created successfully', 200);
    
        } catch (\Throwable $e) {
            DB::rollBack();
            logger()->error($e);
            return response()->json('Failed to create transfer: ' . $e->getMessage(), 500);
        }
    }

    public function show($id){
        try {
            DB::beginTransaction();

            $transfer = Transfer::where('id', $id)
            ->with([
                'items.product.category',
                'dispatch.driver',
                'dispatch.logistic_company',
                'items.inventory_allocations.location',
                'items.inventory_allocations.warehouse',
                'items.inventory_allocations.differences', 
                'backorders', 
                'toFacility', 
                'fromFacility',
                'toWarehouse',
                'fromWarehouse',
                'user',
                'reviewedBy', 
                'approvedBy', 
                'processedBy',
                'dispatchedBy',
                'deliveredBy',
                'receivedBy'
            ])
            ->first();

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
            return inertia('Transfer/Show', [
                'transfer' => $transfer,
                'drivers' => $drivers,
                'companyOptions' => $companyOptions
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            logger()->error('Transfer show error: ' . $th->getMessage());
            logger()->error('Stack trace: ' . $th->getTraceAsString());
            return redirect()->back()->with('error', 'An error occurred while loading the transfer');    
        }
    }
    
    public function create(Request $request){
        if (!auth()->user()->hasPermission('transfer-create') && !auth()->user()->isAdmin) {
            abort(403, 'You do not have permission to create transfers.');
        }
        $warehouses = Warehouse::select('id','name')->get();
        $facilities = Facility::select('id','name')->get();
        $transferID = Transfer::generateTransferId();
        
        return inertia('Transfer/Create', [
            'warehouses' => $warehouses,
            'facilities' => $facilities,
            'transferID' => $transferID,
            'reasons' => Reason::pluck('name')->toArray()
        ]);
    }
    
    /**
     * Delete a transfer item
     */
    // destroyItem method removed

    // get transfer source imventory
    public function getSourceInventoryDetail(Request $request)
    {
        try {
            return DB::transaction(function() use ($request){
                $request->validate([
                    'source_type' => 'required|in:warehouse,facility',
                    'source_id' => 'required|integer',
                    'product_id' => 'required|integer',
                ]);
                
                // Get current date for expiry comparison
                $currentDate = Carbon::now()->toDateString();
                
                if ($request->source_type === 'warehouse') {
                    $inventory = InventoryItem::where('product_id', $request->product_id)
                        ->where('warehouse_id', $request->source_id)
                        ->where('quantity', '>', 0)
                        ->where(function($query) use ($currentDate) {
                            $query->whereNull('expiry_date')
                                  ->orWhere('expiry_date', '>=', $currentDate);
                        })
                        ->with('warehouse:id,name','product:id,name')
                        ->get();
                } else {
                    $inventory = FacilityInventoryItem::where('product_id', $request->product_id)
                        ->whereHas('inventory', function($query) use ($request) {
                            $query->where('facility_id', $request->source_id);
                        })
                        ->where('quantity', '>', 0)
                        ->where(function($query) use ($currentDate) {
                            $query->whereNull('expiry_date')
                                  ->orWhere('expiry_date', '>=', $currentDate);
                        })
                        ->with('product:id,name')
                        ->get();
                }
                
                // Check if no valid inventory items are available
                if ($inventory->isEmpty()) {
                    return response()->json('No available inventory items for transfer', 500);
                }
                
                return response()->json($inventory, 200);
            });
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function updateItem(Request $request){
        try {
            DB::beginTransaction();
            
            $request->validate([
                'id' => 'required|exists:transfer_items,id',
                'quantity' => 'required|numeric|min:1',
            ]);
            
            $transferItem = TransferItem::with('transfer')->findOrFail($request->id);
            $transfer = $transferItem->transfer;

            if($transferItem->quantity <= 0) {
                $transferItem->quantity = $request->quantity;
                $transferItem->save();
                $transferItem->refresh();
            }

            if (!in_array($transfer->status, ['pending'])) {
                return response()->json('Cannot update quantity for transfers that are not in pending status', 500);
            }

            // Use the requested quantity directly for transfers
            $newQuantityToRelease = (int) ceil($request->quantity);
            $oldQuantityToRelease = $transferItem->quantity_to_release ?? 0;

            // Determine source type (warehouse or facility)
            $isFromWarehouse = !empty($transfer->from_warehouse_id);
            $sourceId = $transfer->from_warehouse_id ?? $transfer->from_facility_id;

            // Case 1: Decrease
            if ($newQuantityToRelease < $oldQuantityToRelease) {
                $quantityToRemove = $oldQuantityToRelease - $newQuantityToRelease;
                $remainingToRemove = $quantityToRemove;

                $allocations = $transferItem->inventory_allocations()->orderBy('expiry_date', 'desc')->get();

                foreach ($allocations as $allocation) {
                    if ($remainingToRemove <= 0) break;

                    if ($isFromWarehouse) {
                        // Handle warehouse inventory
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
                            InventoryItem::create([
                                'product_id'   => $allocation->product_id,
                                'warehouse_id' => $allocation->warehouse_id,
                                'location_id'  => $allocation->location_id,
                                'batch_number' => $allocation->batch_number,
                                'uom'          => $allocation->uom,
                                'barcode'      => $allocation->barcode,
                                'expiry_date'  => $allocation->expiry_date,
                                'quantity'     => $restoreQty
                            ]);
                        }
                    } else {
                        // Handle facility inventory
                        $facilityInventory = FacilityInventory::where('facility_id', $sourceId)
                            ->where('product_id', $allocation->product_id)
                            ->first();

                        if ($facilityInventory) {
                            $facilityInventoryItem = FacilityInventoryItem::where('facility_inventory_id', $facilityInventory->id)
                                ->where('batch_number', $allocation->batch_number)
                                ->where('expiry_date', $allocation->expiry_date)
                                ->first();

                            $restoreQty = min($allocation->allocated_quantity, $remainingToRemove);

                            if ($facilityInventoryItem) {
                                $facilityInventoryItem->quantity += $restoreQty;
                                $facilityInventoryItem->save();
                            } else {
                                FacilityInventoryItem::create([
                                    'facility_inventory_id' => $facilityInventory->id,
                                    'batch_number' => $allocation->batch_number,
                                    'uom'          => $allocation->uom,
                                    'barcode'      => $allocation->barcode,
                                    'expiry_date'  => $allocation->expiry_date,
                                    'quantity'     => $restoreQty
                                ]);
                            }
                        }
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

                $transferItem->quantity_to_release = $newQuantityToRelease;
                $transferItem->save();

                DB::commit();
                return response()->json('Quantity to release decreased successfully', 200);
            }

            // Case 2: Increase
            if ($newQuantityToRelease > $oldQuantityToRelease) {
                $quantityToAdd = $newQuantityToRelease - $oldQuantityToRelease;
                $remainingToAllocate = $quantityToAdd;

                if ($isFromWarehouse) {
                    // Handle warehouse inventory
                    $inventoryItems = InventoryItem::where('product_id', $transferItem->product_id)
                        ->where('warehouse_id', $sourceId)
                        ->where('quantity', '>', 0)
                        ->where(function($query) {
                            $query->where('expiry_date', '>', \Carbon\Carbon::now())
                                  ->orWhereNull('expiry_date');
                        })
                        ->orderBy('expiry_date', 'asc')
                        ->get();

                    if ($inventoryItems->isEmpty()) {
                        DB::rollBack();
                        return response()->json('No inventory available for this product in the warehouse', 500);
                    }

                    foreach ($inventoryItems as $inventory) {
                        if ($remainingToAllocate <= 0) break;

                        $allocQty = min($inventory->quantity, $remainingToAllocate);

                        $existingAllocation = $transferItem->inventory_allocations()
                            ->where('batch_number', $inventory->batch_number)
                            ->where('expiry_date', $inventory->expiry_date)
                            ->first();

                        if ($existingAllocation) {
                            $existingAllocation->allocated_quantity += $allocQty;
                            $existingAllocation->save();
                        } else {
                            $transferItem->inventory_allocations()->create([
                                'product_id'       => $inventory->product_id,
                                'warehouse_id'     => $inventory->warehouse_id,
                                'location_id'      => $inventory->location_id,
                                'batch_number'     => $inventory->batch_number,
                                'uom'              => $inventory->uom,
                                'barcode'          => $inventory->barcode ?? null,
                                'expiry_date'      => $inventory->expiry_date,
                                'allocated_quantity' => $allocQty,
                                'allocation_type'  => $transfer->transfer_type,
                                'unit_cost'        => $inventory->unit_cost,
                                'total_cost'       => $inventory->unit_cost * $allocQty,
                                'notes'            => 'Allocated from warehouse inventory ID: ' . $inventory->id
                            ]);
                        }

                        $inventory->quantity -= $allocQty;
                        $inventory->save();
                        $remainingToAllocate -= $allocQty;
                    }
                } else {
                    // Handle facility inventory
                    $facilityInventory = FacilityInventory::where('facility_id', $sourceId)
                        ->where('product_id', $transferItem->product_id)
                        ->first();

                    if (!$facilityInventory) {
                        DB::rollBack();
                        return response()->json('No inventory available for this product in the facility', 500);
                    }

                    $facilityInventoryItems = FacilityInventoryItem::where('facility_inventory_id', $facilityInventory->id)
                        ->where('quantity', '>', 0)
                        ->where(function($query) {
                            $query->where('expiry_date', '>', \Carbon\Carbon::now())
                                  ->orWhereNull('expiry_date');
                        })
                        ->orderBy('expiry_date', 'asc')
                        ->get();

                    if ($facilityInventoryItems->isEmpty()) {
                        DB::rollBack();
                        return response()->json('No inventory available for this product in the facility', 500);
                    }

                    foreach ($facilityInventoryItems as $facilityItem) {
                        if ($remainingToAllocate <= 0) break;

                        $allocQty = min($facilityItem->quantity, $remainingToAllocate);

                        $existingAllocation = $transferItem->inventory_allocations()
                            ->where('batch_number', $facilityItem->batch_number)
                            ->where('expiry_date', $facilityItem->expiry_date)
                            ->first();

                        if ($existingAllocation) {
                            $existingAllocation->allocated_quantity += $allocQty;
                            $existingAllocation->save();
                        } else {
                            $transferItem->inventory_allocations()->create([
                                'product_id'       => $facilityInventory->product_id,
                                'facility_id'      => $sourceId,
                                'batch_number'     => $facilityItem->batch_number,
                                'uom'              => $facilityItem->uom,
                                'barcode'          => $facilityItem->barcode ?? null,
                                'expiry_date'      => $facilityItem->expiry_date,
                                'allocated_quantity' => $allocQty,
                                'allocation_type'  => $transfer->transfer_type,
                                'unit_cost'        => 0, // Facility items might not have unit cost
                                'total_cost'       => 0,
                                'notes'            => 'Allocated from facility inventory ID: ' . $facilityItem->id
                            ]);
                        }

                        $facilityItem->quantity -= $allocQty;
                        $facilityItem->save();
                        $remainingToAllocate -= $allocQty;
                    }
                }

                // Final adjustment
                $totalAllocated = $transferItem->inventory_allocations()->sum('allocated_quantity');
                if ($totalAllocated < $newQuantityToRelease) {
                    $difference = $newQuantityToRelease - $totalAllocated;
                    $lastAllocation = $transferItem->inventory_allocations()->latest()->first();

                    if ($lastAllocation) {
                        $lastAllocation->allocated_quantity += $difference;
                        $lastAllocation->save();

                        if ($isFromWarehouse) {
                            $inventory = InventoryItem::where('product_id', $lastAllocation->product_id)
                                ->where('warehouse_id', $lastAllocation->warehouse_id)
                                ->where('batch_number', $lastAllocation->batch_number)
                                ->where('expiry_date', $lastAllocation->expiry_date)
                                ->first();

                            if ($inventory) {
                                $inventory->quantity -= $difference;
                                $inventory->save();
                            }
                        } else {
                            $facilityInventory = FacilityInventory::where('facility_id', $sourceId)
                                ->where('product_id', $lastAllocation->product_id)
                                ->first();

                            if ($facilityInventory) {
                                $facilityItem = FacilityInventoryItem::where('facility_inventory_id', $facilityInventory->id)
                                    ->where('batch_number', $lastAllocation->batch_number)
                                    ->where('expiry_date', $lastAllocation->expiry_date)
                                    ->first();

                                if ($facilityItem) {
                                    $facilityItem->quantity -= $difference;
                                    $facilityItem->save();
                                }
                            }
                        }
                    }
                }

                if ($remainingToAllocate > 0) {
                    DB::rollBack();
                    $sourceType = $isFromWarehouse ? 'warehouse' : 'facility';
                    return response()->json("Insufficient inventory in {$sourceType}. Could only allocate " . ($quantityToAdd - $remainingToAllocate) . ' out of ' . $quantityToAdd, 500);
                }

                $transferItem->quantity_to_release = $newQuantityToRelease;
                $transferItem->save();

               // event(new InventoryUpdated());

                DB::commit();
                return response()->json('Quantity to release updated successfully', 200);
            }

            // No change
            DB::commit();
            return response()->json('No change in quantity to release', 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
    
    public function saveBackOrders(Request $request)
    {
        try {
            DB::beginTransaction();
            
            $request->validate([
                'transfer_id' => 'required|exists:transfers,id',
                'packing_list_differences' => 'required|array',
                'packing_list_differences.*.quantity' => 'required|numeric|min:0',
                'packing_list_differences.*.status' => 'required|in:Missing,Damaged,Expired,Lost,Low Quality',
                'packing_list_differences.*.notes' => 'nullable|string',
                'packing_list_differences.*.transfer_item_id' => 'required|exists:transfer_items,id',
            ]);

            $transfer = Transfer::find($request->transfer_id);
            if(!$transfer) {
                return response()->json('Transfer not found', 500);
            }

            // Find or create BackOrder for this transfer
            $backOrder = BackOrder::where('transfer_id', $request->transfer_id)->first();

            if(!$backOrder) {
                $backOrder = BackOrder::create([
                    'transfer_id' => $request->transfer_id,
                    'back_order_date' => now()->toDateString(),
                    'created_by' => auth()->user()->id,
                    'source_type' => 'transfer',
                    'reported_by' => $transfer->toFacility->name ?? 'Unknown Facility',
                    'total_items' => 0,
                    'total_quantity' => 0,
                ]);
            }

            // Process packing list differences
            $totalQuantity = 0;
            $totalItems = 0;
            
            foreach ($request->packing_list_differences as $differenceData) {
                $transferItem = TransferItem::find($differenceData['transfer_item_id']);
                if(!$transferItem) {
                    throw new \Exception('Transfer item not found: ' . $differenceData['transfer_item_id']);
                }
                
                if (isset($differenceData['id']) && $differenceData['id']) {
                    // Update existing difference
                    $difference = PackingListDifference::find($differenceData['id']);
                    if ($difference) {
                        $difference->update([
                            'back_order_id' => $backOrder->id,
                            'product_id' => $transferItem->product_id,
                            'inventory_allocation_id' => $differenceData['inventory_allocation_id'] ?? null,
                            'quantity' => $differenceData['quantity'],
                            'status' => $differenceData['status'],
                            'notes' => $differenceData['notes'] ?? null,
                        ]);
                    }
                } else {
                    // Create new difference
                    $difference = PackingListDifference::create([
                        'back_order_id' => $backOrder->id,
                        'product_id' => $transferItem->product_id,
                        'inventory_allocation_id' => $differenceData['inventory_allocation_id'] ?? null,
                        'quantity' => $differenceData['quantity'],
                        'status' => $differenceData['status'],
                        'notes' => $differenceData['notes'] ?? null,
                    ]);
                }
                
                $totalQuantity += $differenceData['quantity'];
                $totalItems++;
            }

            // Update BackOrder totals
            $backOrder->update([
                'total_items' => $totalItems,
                'total_quantity' => $totalQuantity,
            ]);

            //// event(new \App\Events\InventoryUpdated($transfer->from_facility_id));

            DB::commit();
            return response()->json('Back orders saved successfully', 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }   

    /**
     * Helper method to update transfer status based on item completion
     */
    private function updateTransferStatusIfNeeded($transfer)
    {
        $allItemsProcessed = $transfer->items->every(function ($item) {
            $missingQuantity = $item->quantity_to_release - ($item->received_quantity ?? 0);
            $existingBackOrders = $item->inventory_allocations()
                ->whereHas('differences')
                ->with('differences')
                ->get()
                ->flatMap(function($allocation) {
                    return $allocation->differences;
                })
                ->sum('quantity');
            
            return $missingQuantity <= $existingBackOrders;
        });

        if ($allItemsProcessed && $transfer->status === 'shipped') {
            $transfer->status = 'received';
            $transfer->save();
        }
    }
    
    /**
     * Get inventories based on source type and ID
     */
    public function getInventories(Request $request)
    {
        $request->validate([
            'source_type' => 'required|in:warehouse,facility',
            'source_id' => 'required|integer',
        ]);
        
        try {
            if ($request->source_type === 'warehouse') {
                // Get warehouse inventories directly with DB query
                $products = Product::whereHas('inventories.items', function($query) use ($request) {
                    $query->where('warehouse_id', $request->source_id);
                })             
                    ->get();
                
                return response()->json($products, 200);
            } else {
                // Get facility inventories directly with DB query
                $products = Product::whereHas('facilityInventories', function($query) use ($request) {
                    $query->where('facility_id', $request->source_id)
                          ->whereHas('items', function($subQuery) {
                              $subQuery->where('quantity', '>', 0);
                          });
                })
                    ->get();
                
                return response()->json($products, 200);
            }
        } catch (\Throwable $th) {
            logger()->info($th->getMessage());
            return response()->json($th->getMessage(), 500);
        }
    }

    public function updateQuantity(Request $request){
        try {
            DB::beginTransaction();

            $request->validate([
                'allocation_id'  => 'required|exists:inventory_allocations,id',
                'quantity' => 'required|numeric'
            ]);

            $allocation = InventoryAllocation::findOrFail($request->allocation_id);
            $transferItem = $allocation->transfer_item;
            $transfer = $transferItem->transfer;

            // Update the specific allocation quantity
            $oldUpdatedQuantity = $allocation->updated_quantity ?? 0;
            $newUpdatedQuantity = (int) ceil($request->quantity);
            
            // Calculate the difference in updated quantity
            $quantityDifference = $newUpdatedQuantity - $oldUpdatedQuantity;
            
            if ($quantityDifference == 0) {
                DB::commit();
                return response()->json('No change in quantity', 200);
            }

            // Determine source type (warehouse or facility)
            $isFromWarehouse = !empty($transfer->from_warehouse_id);
            $sourceId = $transfer->from_warehouse_id ?? $transfer->from_facility_id;

            // Case 1: Decrease allocation quantity
            if ($quantityDifference < 0) {
                $quantityToRestore = abs($quantityDifference);
                
                // Restore quantity to inventory
                if ($isFromWarehouse) {
                    // Handle warehouse inventory
                    $inventory = InventoryItem::where('product_id', $allocation->product_id)
                        ->where('warehouse_id', $allocation->warehouse_id)
                        ->where('batch_number', $allocation->batch_number)
                        ->where('expiry_date', $allocation->expiry_date)
                        ->first();

                    if ($inventory) {
                        $inventory->quantity += $quantityToRestore;
                        $inventory->save();
                    } else {
                        // First, get or create the main inventory record
                        $mainInventory = Inventory::firstOrCreate([
                            'product_id' => $allocation->product_id,
                        ], [
                            'quantity' => 0,
                        ]);
                        
                        InventoryItem::create([
                            'inventory_id' => $mainInventory->id,
                            'product_id'   => $allocation->product_id,
                            'warehouse_id' => $allocation->warehouse_id,
                            'location_id'  => $allocation->location_id,
                            'batch_number' => $allocation->batch_number,
                            'uom'          => $allocation->uom,
                            'barcode'      => $allocation->barcode,
                            'expiry_date'  => $allocation->expiry_date,
                            'quantity'     => $quantityToRestore
                        ]);
                    }
                } else {
                    // Handle facility inventory
                    $facilityInventory = FacilityInventory::where('facility_id', $sourceId)
                        ->where('product_id', $allocation->product_id)
                        ->first();

                    if ($facilityInventory) {
                        $facilityInventoryItem = FacilityInventoryItem::where('facility_inventory_id', $facilityInventory->id)
                            ->where('batch_number', $allocation->batch_number)
                            ->where('expiry_date', $allocation->expiry_date)
                            ->first();

                        if ($facilityInventoryItem) {
                            $facilityInventoryItem->quantity += $quantityToRestore;
                            $facilityInventoryItem->save();
                        } else {
                            FacilityInventoryItem::create([
                                'facility_inventory_id' => $facilityInventory->id,
                                'batch_number' => $allocation->batch_number,
                                'uom'          => $allocation->uom,
                                'barcode'      => $allocation->barcode,
                                'expiry_date'  => $allocation->expiry_date,
                                'quantity'     => $quantityToRestore
                            ]);
                        }
                    }
                }

                // Update allocation updated_quantity
                $allocation->updated_quantity = $newUpdatedQuantity;
                $allocation->save();

                // Update transfer item total quantity (sum of original allocated_quantity + updated_quantity for all allocations)
                $totalQuantity = $transferItem->inventory_allocations->sum(function($alloc) {
                    return $alloc->allocated_quantity + ($alloc->updated_quantity ?? 0);
                });
                $transferItem->quantity_to_release = $totalQuantity;
                $transferItem->save();

                // Dispatch inventory updated event
               // event(new InventoryUpdated($sourceId));

                DB::commit();
                return response()->json('Allocation quantity decreased successfully', 200);
            }

            // Case 2: Increase allocation quantity
            if ($quantityDifference > 0) {
                $quantityToAllocate = $quantityDifference;
                
                // Check if we have enough inventory
                if ($isFromWarehouse) {
                    // Handle warehouse inventory
                    $inventory = InventoryItem::where('product_id', $allocation->product_id)
                        ->where('warehouse_id', $allocation->warehouse_id)
                        ->where('batch_number', $allocation->batch_number)
                        ->where('expiry_date', $allocation->expiry_date)
                        ->where('quantity', '>=', $quantityToAllocate)
                        ->first();

                    if (!$inventory) {
                        DB::rollBack();
                        return response()->json('Insufficient inventory available for this allocation', 400);
                    }

                    $inventory->quantity -= $quantityToAllocate;
                    $inventory->save();
                } else {
                    // Handle facility inventory
                    $facilityInventory = FacilityInventory::where('facility_id', $sourceId)
                        ->where('product_id', $allocation->product_id)
                        ->first();

                    if (!$facilityInventory) {
                        DB::rollBack();
                        return response()->json('No inventory available for this product in the facility', 400);
                    }

                    $facilityInventoryItem = FacilityInventoryItem::where('facility_inventory_id', $facilityInventory->id)
                        ->where('batch_number', $allocation->batch_number)
                        ->where('expiry_date', $allocation->expiry_date)
                        ->where('quantity', '>=', $quantityToAllocate)
                        ->first();

                    if (!$facilityInventoryItem) {
                        DB::rollBack();
                        return response()->json('Insufficient inventory available for this allocation', 400);
                    }

                    $facilityInventoryItem->quantity -= $quantityToAllocate;
                    $facilityInventoryItem->save();
                }

                // Update allocation updated_quantity
                $allocation->updated_quantity = $newUpdatedQuantity;
                $allocation->save();

                // Update transfer item total quantity (sum of original allocated_quantity + updated_quantity for all allocations)
                $totalQuantity = $transferItem->inventory_allocations->sum(function($alloc) {
                    return $alloc->allocated_quantity + ($alloc->updated_quantity ?? 0);
                });
                $transferItem->quantity_to_release = $totalQuantity;
                $transferItem->save();

                // Dispatch inventory updated event
               // event(new InventoryUpdated($sourceId));

                DB::commit();
                return response()->json('Allocation quantity increased successfully', 200);
            }

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }
    
    // deleteBackOrder method removed

        
    public function receivedQuantity(Request $request){
        try {
            return DB::transaction(function () use ($request) {
                $request->validate([
                    'allocation_id' => 'required|exists:inventory_allocations,id',
                    'received_quantity' => 'required|numeric|min:0',
                ]);
                
                $allocation = InventoryAllocation::find($request->allocation_id);
                                
                $allocation->received_quantity = $request->received_quantity;
                $allocation->save();
                PackingListDifference::where('inventory_allocation_id', $request->allocation_id)->delete();
    
                return response()->json("Success", 200);    
                
            });
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function receivedAllocationQuantity(Request $request){
        try {
            $request->validate([
                'allocation_id' => 'required|exists:inventory_allocations,id',
                'received_quantity' => 'required|numeric|min:0',
            ]);

            $allocation = InventoryAllocation::find($request->allocation_id);
            $transferItem = $allocation->transfer_item;
            $transfer = $transferItem->transfer;

            // Validate that user belongs to the receiving facility/warehouse
            $currentUser = auth()->user();
            $isReceiver = ($transfer->to_warehouse_id === $currentUser->warehouse_id) || 
                         ($transfer->to_facility_id === $currentUser->facility_id);

            if (!$isReceiver) {
                return response()->json("You are not authorized to receive this transfer", 403);
            }

            // Use update_quantity if it's set (not zero), otherwise use allocated_quantity
            $effectiveQuantity = ($allocation->updated_quantity ?? 0) !== 0 ? $allocation->updated_quantity : $allocation->allocated_quantity;
            
            // Validate received quantity doesn't exceed allocated quantity
            if ($request->received_quantity > $effectiveQuantity) {
                return response()->json("Received quantity cannot exceed allocated quantity", 400);
            }

            // Calculate total PackingListDifference quantities for this allocation
            $totalDifferences = $allocation->differences()->sum('quantity');
            
            // Validate that: allocated_quantity - received_quantity = total_differences
            $expectedReceivedQuantity = $effectiveQuantity - $totalDifferences;
            
            if ($request->received_quantity != $expectedReceivedQuantity) {
                return response()->json([
                    "message" => "Received quantity must equal allocated quantity minus differences",
                    "allocated_quantity" => $effectiveQuantity,
                    "total_differences" => $totalDifferences,
                    "expected_received_quantity" => $expectedReceivedQuantity,
                    "provided_received_quantity" => $request->received_quantity
                ], 400);
            }

            // Update allocation received quantity
            $allocation->received_quantity = $request->received_quantity;
            $allocation->save();

            // Update transfer item received quantity (sum of all allocations)
            $totalReceived = $transferItem->inventory_allocations->sum('received_quantity');
            $transferItem->received_quantity = $totalReceived;
            $transferItem->save();

            // Dispatch inventory updated event
           // event(new InventoryUpdated($transfer->from_warehouse_id ?? $transfer->from_facility_id));

            return response()->json('Allocation received quantity updated successfully', 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }


    public function dispatchInfo(Request $request){
        try {
            return DB::transaction(function() use ($request){
                $request->validate([
                    'dispatch_date' => 'required|date',
                    'driver_id' => 'required|exists:drivers,id',
                    'driver_number' => 'required|string',
                    'plate_number' => 'required|string',
                    'no_of_cartoons' => 'required|numeric',
                    'transfer_id' => 'required|exists:transfers,id',
                    'logistic_company_id' => 'required|exists:logistic_companies,id',
                    'status' => 'required|string'
                ]);

                $transfer = Transfer::with('dispatch')->find($request->transfer_id);
                $transfer->dispatch()->create([
                    'transfer_id' => $request->transfer_id,
                    'dispatch_date' => $request->dispatch_date,
                    'driver_id' => $request->driver_id,
                    'logistic_company_id' => $request->logistic_company_id,
                    'driver_number' => $request->driver_number,
                    'plate_number' => $request->plate_number,
                    'no_of_cartoons' => $request->no_of_cartoons,
                ]);

                $transfer->status = $request->status;
                $transfer->dispatched_at = now();
                $transfer->dispatched_by = auth()->user()->id;
                $transfer->save();
                
                return response()->json("Dispatched Successfully", 200);
            });
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    
    /**
     * Change transfer status with proper permissions and workflow validation
     */
    public function changeStatus(Request $request)
    {
        try {
            DB::beginTransaction();
            
            $request->validate([
                'transfer_id' => 'required|exists:transfers,id',
                'status' => 'required|string|in:pending,reviewed,approved,in_process,dispatched,delivered,received,rejected'
            ]);

            $transfer = Transfer::with('items.inventory_allocations.differences')->find($request->transfer_id);
            $newStatus = $request->status;
            $oldStatus = $transfer->status;
            $user = auth()->user();

            // Define status progression order
            $statusOrder = ['pending', 'reviewed', 'approved', 'in_process', 'dispatched', 'delivered', 'received','rejected'];
            $currentStatusIndex = array_search($transfer->status, $statusOrder);
            $newStatusIndex = array_search($newStatus, $statusOrder);

            // Validate status progression (can only move forward, except for certain cases)
            if ($newStatusIndex <= $currentStatusIndex && $newStatus !== $transfer->status) {
                DB::rollBack();
                return response()->json('Cannot move backwards in transfer workflow', 400);
            }

            // Permission checks based on status
            switch ($newStatus) {
                case 'reviewed':
                    // Allow either specific action permission or full transfer management
                    // NOTE: permissions in DB are dash-based (e.g. transfer-review), while Vue uses underscores.
                    if (!$user->can('transfer-review') && !$user->can('transfer-manage')) {
                        DB::rollBack();
                        return response()->json('You do not have permission to review transfers', 403);
                    }
                    if ($transfer->status !== 'pending') {
                        DB::rollBack();
                        return response()->json('Transfer must be pending to review', 400);
                    }
                    $transfer->reviewed_at = now();
                    $transfer->reviewed_by = $user->id;
                    break;

                case 'approved':
                    if (!$user->can('transfer-approve') && !$user->can('transfer-manage')) {
                        DB::rollBack();
                        return response()->json('You do not have permission to approve transfers', 403);
                    }
                    if ($transfer->status !== 'reviewed') {
                        DB::rollBack();
                        return response()->json('Transfer must be reviewed to approve', 400);
                    }
                    $transfer->approved_at = now();
                    $transfer->approved_by = $user->id;
                    break;

                case 'in_process':
                    // Can be done by from warehouse/facility staff
                    if (!$user->can('transfer-processing') && !$user->can('transfer-manage') && $transfer->status == 'approved') {
                        DB::rollBack();
                        return response()->json('You do not have permission to process transfers', 403);
                    }
                    $transfer->processed_at = now();
                    $transfer->processed_by = $user->id;
                    break;

                case 'dispatched':
                    // Can be done by from warehouse/facility staff
                    if (!$user->can('transfer-dispatch') && !$user->can('transfer-manage')) {
                        DB::rollBack();
                        return response()->json('You do not have permission to dispatch transfers', 403);
                    }
                    if ($transfer->status !== 'in_process') {
                        DB::rollBack();
                        return response()->json('Transfer must be in process to dispatch', 400);
                    }
                    $transfer->dispatched_at = now();
                    $transfer->dispatched_by = $user->id;
                    break;

                case 'delivered':
                    // Can be done by to warehouse/facility staff
                    if (!$user->can('transfer-delivery') && !$user->can('transfer-manage')) {
                        DB::rollBack();
                        return response()->json('You do not have permission to mark transfers as delivered', 403);
                    }
                    if ($user->warehouse_id !== $transfer->to_warehouse_id && 
                        $user->facility_id !== $transfer->to_facility_id) {
                        DB::rollBack();
                        return response()->json('You can only mark transfers as delivered to your warehouse/facility', 403);
                    }
                    if ($transfer->status !== 'dispatched') {
                        DB::rollBack();
                        return response()->json('Transfer must be dispatched to deliver', 400);
                    }
                    $transfer->delivered_at = now();
                    $transfer->delivered_by = $user->id;
                    break;

                case 'rejected':
                    if (!$user->can('transfer-reject') && !$user->can('transfer-manage')) {
                        DB::rollBack();
                        return response()->json('You do not have permission to reject transfers', 403);
                    }
                    if ($transfer->status !== 'reviewed') {
                        DB::rollBack();
                        return response()->json('Transfer must be reviewed to reject', 400);
                    }
                    $transfer->rejected_at = now();
                    $transfer->rejected_by = $user->id;
                    
                    // Rollback all the inventory allocations
                    foreach ($transfer->items as $transferItem) {
                        foreach ($transferItem->inventory_allocations as $allocation) {
                            // Determine if source is warehouse or facility
                            if ($transfer->from_warehouse_id) {
                                // Source is warehouse - restore to warehouse inventory
                                $inventoryItem = InventoryItem::where('product_id', $allocation->product_id)
                                    ->where('warehouse_id', $transfer->from_warehouse_id)
                                    ->where('batch_number', $allocation->batch_number)
                                    ->where('expiry_date', $allocation->expiry_date)
                                    ->first();

                                if ($inventoryItem) {
                                    // Add back the allocated quantity
                                    $inventoryItem->quantity += $allocation->allocated_quantity;
                                    $inventoryItem->save();
                                } else {
                                    // Create new inventory item if it doesn't exist
                                    InventoryItem::create([
                                        'product_id' => $allocation->product_id,
                                        'warehouse_id' => $transfer->from_warehouse_id,
                                        'location_id' => $allocation->location_id,
                                        'batch_number' => $allocation->batch_number,
                                        'uom' => $allocation->uom,
                                        'barcode' => $allocation->barcode,
                                        'expiry_date' => $allocation->expiry_date,
                                        'quantity' => $allocation->allocated_quantity,
                                        'unit_cost' => $allocation->unit_cost ?? 0,
                                        'total_cost' => ($allocation->unit_cost ?? 0) * $allocation->allocated_quantity,
                                    ]);
                                }
                            } else if ($transfer->from_facility_id) {
                                // Source is facility - restore to facility inventory
                                $facilityInventory = FacilityInventory::where('facility_id', $transfer->from_facility_id)
                                    ->where('product_id', $allocation->product_id)
                                    ->first();

                                if ($facilityInventory) {
                                    $facilityInventoryItem = FacilityInventoryItem::where('facility_inventory_id', $facilityInventory->id)
                                        ->where('batch_number', $allocation->batch_number)
                                        ->where('expiry_date', $allocation->expiry_date)
                                        ->first();

                                    if ($facilityInventoryItem) {
                                        // Add back the allocated quantity
                                        $facilityInventoryItem->quantity += $allocation->allocated_quantity;
                                        $facilityInventoryItem->save();
                                    } else {
                                        // Create new facility inventory item if it doesn't exist
                                        FacilityInventoryItem::create([
                                            'facility_inventory_id' => $facilityInventory->id,
                                            'product_id' => $allocation->product_id,
                                            'batch_number' => $allocation->batch_number,
                                            'uom' => $allocation->uom,
                                            'barcode' => $allocation->barcode,
                                            'expiry_date' => $allocation->expiry_date,
                                            'quantity' => $allocation->allocated_quantity,
                                            'unit_cost' => $allocation->unit_cost ?? 0,
                                            'total_cost' => ($allocation->unit_cost ?? 0) * $allocation->allocated_quantity,
                                        ]);
                                    }
                                } else {
                                    // Create new facility inventory if it doesn't exist
                                    $facilityInventory = FacilityInventory::create([
                                        'facility_id' => $transfer->from_facility_id,
                                        'product_id' => $allocation->product_id,
                                    ]);

                                    FacilityInventoryItem::create([
                                        'facility_inventory_id' => $facilityInventory->id,
                                        'product_id' => $allocation->product_id,
                                        'batch_number' => $allocation->batch_number,
                                        'uom' => $allocation->uom,
                                        'barcode' => $allocation->barcode,
                                        'expiry_date' => $allocation->expiry_date,
                                        'quantity' => $allocation->allocated_quantity,
                                        'unit_cost' => $allocation->unit_cost ?? 0,
                                        'total_cost' => ($allocation->unit_cost ?? 0) * $allocation->allocated_quantity,
                                    ]);
                                }
                            }
                        }
                        
                        // Delete all inventory allocations for this transfer item
                        $transferItem->inventory_allocations()->delete();
                    }
                    
                    break;

                case 'received':
                    // Can be done by to warehouse/facility staff
                    if (!$user->can('transfer-receive') && !$user->can('transfer-manage')) {
                        DB::rollBack();
                        return response()->json('You do not have permission to receive transfers', 403);
                    }
                    if ($user->warehouse_id !== $transfer->to_warehouse_id && 
                        $user->facility_id !== $transfer->to_facility_id) {
                        DB::rollBack();
                        return response()->json('You can only receive transfers to your warehouse/facility', 403);
                    }
                    if ($transfer->status !== 'delivered') {
                        DB::rollBack();
                        return response()->json('Transfer must be delivered to receive', 400);
                    }
                    foreach ($transfer->items as $item) {
                        foreach ($item->inventory_allocations as $allocation) {
                            // Calculate total back order quantity for this allocation using PackingListDifference
                            $backOrderQuantity = $allocation->differences()->whereNull('finalized')->sum('quantity');
                            
                            // Use updated_quantity if it's set (not zero), otherwise use allocated_quantity
                            $effectiveQuantity = ($allocation->updated_quantity ?? 0) !== 0 ? $allocation->updated_quantity : $allocation->allocated_quantity;
                            
                            if((int) $effectiveQuantity < (int) $backOrderQuantity){
                                DB::rollback();
                                return response()->json('Backorder quantities exceeded the allocated quantity', 500);
                            }
                            $finalQuantity = (int) $effectiveQuantity - (int) $backOrderQuantity;
                            
                            $inventory = Inventory::where('product_id', $allocation->product_id)
                                ->first();
        
                            if($inventory){
                                $inventoryItem = $inventory->items()
                                    ->where('batch_number', $allocation->batch_number)
                                    ->where('warehouse_id', $transfer->to_warehouse_id)
                                    ->first();
                                if($inventoryItem){
                                    $inventoryItem->increment('quantity', $finalQuantity);
                                    ReceivedQuantity::create([
                                        'product_id' => $allocation->product_id,
                                        'quantity' => $finalQuantity,
                                        'received_by' => auth()->id(),
                                        'received_at' => now(),
                                        'warehouse_id' => $transfer->to_warehouse_id,
                                        'transfer_id' => $transfer->id,
                                        'expiry_date' => $allocation->expiry_date,
                                        'uom' => $allocation->uom,
                                        'barcode' => $allocation->barcode,
                                        'batch_number' => $allocation->batch_number,
                                        'unit_cost' => $allocation->unit_cost,
                                        'total_cost' => $allocation->unit_cost * $finalQuantity,
                                    ]);
                                }else{
                                    $inventory->items()->create([
                                        'product_id' => $allocation->product_id,
                                        'quantity' => $finalQuantity,
                                        'expiry_date' => $allocation->expiry_date,
                                        'warehouse_id' => $transfer->to_warehouse_id,
                                        'location' => $allocation->location,
                                        'batch_number' => $allocation->batch_number,
                                        'barcode' => $allocation->barcode,
                                        'uom' => $allocation->uom,
                                        'unit_cost' => $allocation->unit_cost,
                                        'total_cost' => $allocation->unit_cost * $finalQuantity
                                    ]);
                                    ReceivedQuantity::create([
                                        'product_id' => $allocation->product_id,
                                        'quantity' => $finalQuantity,
                                        'received_by' => auth()->id(),
                                        'received_at' => now(),
                                        'warehouse_id' => $transfer->to_warehouse_id,
                                        'transfer_id' => $transfer->id,
                                        'expiry_date' => $allocation->expiry_date,
                                        'uom' => $allocation->uom,
                                        'barcode' => $allocation->barcode,
                                        'batch_number' => $allocation->batch_number,
                                        'unit_cost' => $allocation->unit_cost,
                                        'total_cost' => $allocation->unit_cost * $finalQuantity,
                                    ]);
                                }
                                
                            }else{
                                $inventory = Inventory::create([
                                    'product_id' => $allocation->product_id
                                ]);
                                $inventory->items()->create([
                                    'product_id' => $allocation->product_id,
                                    'batch_number' => $allocation->batch_number,
                                    'expiry_date' => $allocation->expiry_date,
                                    'quantity' => $finalQuantity,
                                    'barcode' => $allocation->barcode,
                                    'warehouse_id' => $transfer->to_warehouse_id,
                                    'location' => $allocation->location,
                                    'uom' => $allocation->uom,
                                    'unit_cost' => $allocation->unit_cost,
                                    'total_cost' => $allocation->unit_cost * $finalQuantity
                                ]);
                                ReceivedQuantity::create([
                                    'product_id' => $allocation->product_id,
                                    'quantity' => $finalQuantity,
                                    'received_by' => auth()->id(),
                                    'received_at' => now(),
                                    'warehouse_id' => $transfer->to_warehouse_id,
                                    'transfer_id' => $transfer->id,
                                    'expiry_date' => $allocation->expiry_date,
                                    'uom' => $allocation->uom,
                                    'barcode' => $allocation->barcode,
                                    'batch_number' => $allocation->batch_number,
                                    'unit_cost' => $allocation->unit_cost,
                                    'total_cost' => $allocation->unit_cost * $finalQuantity,
                                ]);
                            }
                            
                        }
                    }
                    
                    // Update transfer status to received
                    $transfer->received_at = Carbon::now();
                    $transfer->received_by = auth()->user()->id;
                    break;

                case 'rejected':
                    if ($transfer->status !== 'reviewed') {
                        DB::rollBack();
                        return response()->json('Transfer must be reviewed to reject', 400);
                    }
                    $transfer->rejected_at = now();
                    $transfer->rejected_by = $user->id;
                    break;

                default:
                    DB::rollBack();
                    return response()->json('Invalid status', 400);
            }

            // Update the status
            $transfer->status = $newStatus;
            $transfer->save();

            // Workflow: notify eligible users (by permission) for each next action
            $notifyRecipients = function (string $permission, string $actionConstant) use ($user, $transfer) {
                User::withPermission($permission)
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->where('id', '!=', $user->id)
                    ->get()
                    ->each(fn ($u) => $u->notify(new TransferActionRequired($transfer, $actionConstant)));
            };
            $notifyRecipientsMultiple = function (array $permissions, string $actionConstant) use ($user, $transfer) {
                $recipients = collect();
                foreach ($permissions as $perm) {
                    $recipients = $recipients->merge(
                        User::withPermission($perm)->where('is_active', true)->whereNotNull('email')->where('id', '!=', $user->id)->get()
                    );
                }
                $recipients->unique('id')->each(fn ($u) => $u->notify(new TransferActionRequired($transfer, $actionConstant)));
            };

            if ($newStatus === 'reviewed') {
                $notifyRecipientsMultiple(['transfer-approve', 'transfer-reject'], TransferActionRequired::ACTION_READY_FOR_APPROVAL);
            } elseif ($newStatus === 'approved') {
                $notifyRecipients('transfer-processing', TransferActionRequired::ACTION_READY_FOR_PROCESSING);
            } elseif ($newStatus === 'in_process') {
                $notifyRecipients('transfer-dispatch', TransferActionRequired::ACTION_READY_FOR_DISPATCH);
            } elseif ($newStatus === 'dispatched') {
                $notifyRecipients('transfer-delivery', TransferActionRequired::ACTION_READY_FOR_DELIVERY);
            } elseif ($newStatus === 'delivered') {
                $notifyRecipients('transfer-receive', TransferActionRequired::ACTION_READY_FOR_RECEIVE);
            }

            DB::commit();
            return response()->json('Transfer status updated successfully', 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }
    }
    
    /**
     * Receive a back order
     */
    public function receiveBackOrder(Request $request){
        try {
            DB::beginTransaction();
            
            $request->validate([
                'backorder' => 'required',
                'quantity' => 'required|numeric|min:1',
            ]);
            
            $backorderData = $request->backorder;
            $receivedQuantity = $request->quantity;
            
            // Find the backorder record
            $backorder = FacilityBackorder::findOrFail($backorderData['id']);
            
            // Find the transfer item
            $transferItem = TransferItem::findOrFail($backorder->transfer_item_id);
            
            // Update the received quantity of the transfer item
            $transferItem->received_quantity += $receivedQuantity;
            $transferItem->save();
            
            // Deduct the received quantity from the backorder
            $backorder->quantity -= $receivedQuantity;
            
            // If quantity becomes zero, delete the backorder
            if ($backorder->quantity <= 0) {
                $backorder->delete();
            } else {
                // Otherwise, save the updated quantity
                $backorder->save();
            }
            
            // Get the warehouse ID from the transfer
            $transfer = Transfer::with('toWarehouse')->findOrFail($transferItem->transfer_id);
            $warehouseId = $transfer->to_warehouse_id;
            
            if (!$warehouseId) {
                throw new \Exception('No destination warehouse found for this transfer');
            }
            
            // Check if inventory exists for this product in the warehouse
            $inventory = Inventory::where('warehouse_id', $warehouseId)
                ->where('product_id', $backorder->product_id)
                ->where('batch_number', $transferItem->batch_number)
                ->where('expiry_date', $transferItem->expire_date)
                ->first();
            
            if ($inventory) {
                // Update existing inventory
                $inventory->quantity += $receivedQuantity;
                $inventory->save();
                ReceivedQuantity::create([
                    'quantity' => $receivedQuantity,
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                    'product_id' => $backorder->product_id,
                    'warehouse_id' => $warehouseId,
                    'transfer_id' => $transfer->id,
                    'expiry_date' => $transferItem->expire_date,
                    'uom' => $transferItem->uom,
                    'barcode' => $transferItem->barcode,
                    'batch_number' => $transferItem->batch_number,
                    'unit_cost' => $transferItem->unit_cost,
                    'total_cost' => $transferItem->unit_cost
                ]);
            } else {
                // Create new inventory record
                $inventory = Inventory::create([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $backorder->product_id,
                    'batch_number' => $transferItem->batch_number,
                    'expiry_date' => $transferItem->expire_date,
                    'barcode' => $transferItem->barcode,
                    'quantity' => $receivedQuantity,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
                ReceivedQuantity::create([
                    'transfer_id' => $transfer->id,
                    'quantity' => $receivedQuantity,
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                    'product_id' => $backorder->product_id,
                    'expiry_date' => $transferItem->expire_date,
                    'uom' => $transferItem->uom,
                    'warehouse_id' => $warehouseId,
                    'unit_cost' => $transferItem->unit_cost,
                    'total_cost' => $transferItem->unit_cost * $receivedQuantity,
                    'barcode' => $transferItem->barcode,
                    'batch_number' => $transferItem->batch_number,
                ]);
            }
            
            // Create backorder history record
            BackOrderHistory::create([
                'packing_list_id' => null, // No packing list for transfer backorders
                'transfer_id' => $transfer->id,
                'product_id' => $backorder->product_id,
                'quantity' => $receivedQuantity,
                'status' => "Received", // 'Missing' or 'Damaged'
                'note' => 'Backorder received and added to inventory',
                'performed_by' => auth()->id()
            ]);
            
            DB::commit();
            
            // Dispatch event for real-time inventory updates
            $inventoryData = [
                'product_id' => $backorder->product_id,
                'warehouse_id' => $warehouseId,
                'quantity' => $inventory->quantity,
                'batch_number' => $transferItem->batch_number,
                'expiry_date' => $transferItem->expire_date,
                'action' => 'received',
                'source' => 'backorder'
            ];
           // event(new \App\Events\InventoryUpdated($inventoryData));
            return response()->json([
                'message' => 'Backorder received successfully'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'error' => $th->getMessage()
            ], 500);
        }
    }
    
    public function restoreTransfer(Request $request)
    {
        try {
            $transfer = Transfer::find($request->transfer_id);
            $transfer->status = 'pending';
            $transfer->rejected_at = null;
            $transfer->rejected_by = null;
            $transfer->reviewed_at = null;
            $transfer->reviewed_by = null;
            $transfer->save();
            return response()->json('Transfer restored successfully.', 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Mark transfer as delivered with delivery form data
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markDelivered(Request $request)
    {
        try {
            DB::beginTransaction();
            
            // Validate request
            $request->validate([
                'transfer_id' => 'required|exists:transfers,id',
                'received_cartons' => 'required|array',
                'received_cartons.*' => 'required|numeric|min:0',
                'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max per image
            ]);
            
            $transfer = Transfer::with(['dispatch'])->findOrFail($request->transfer_id);
            
            // Validate transfer status
            if ($transfer->status !== 'dispatched') {
                return response()->json('Transfer must be in dispatched status to mark as delivered', 400);
            }
            
            $receivedCartons = $request->received_cartons;
            
            // Handle image uploads
            $imagePaths = [];
            if ($request->hasFile('images')) {
                $images = $request->file('images');
                foreach ($images as $image) {
                    if ($image->isValid()) {
                        $filename = 'transfer_delivery_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                        $image->move(public_path('transfer-delivery-images'), $filename);
                        $imagePaths[] = 'transfer-delivery-images/' . $filename;
                    }
                }
            }
            
            // Update dispatch info with received cartons and images
            if ($transfer->dispatch && count($transfer->dispatch) > 0) {
                foreach ($transfer->dispatch as $dispatch) {
                    if (isset($receivedCartons[$dispatch->id])) {
                        $dispatch->received_cartons = $receivedCartons[$dispatch->id];
                        // Save images to the first dispatch record
                        if (!empty($imagePaths)) {
                            $dispatch->image = json_encode($imagePaths);
                        }
                        $dispatch->save();
                    }
                }
            }
            
            // Update transfer status to delivered
            $transfer->status = 'delivered';
            $transfer->delivered_at = Carbon::now();
            $transfer->delivered_by = auth()->user()->id;
            
            $transfer->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Transfer marked as delivered successfully',
                'data' => [
                    'transfer_id' => $transfer->id,
                    'status' => $transfer->status,
                    'delivered_at' => $transfer->delivered_at,
                    'images_uploaded' => count($imagePaths)
                ]
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark transfer as delivered: ' . $e->getMessage()
            ], 500);
        }
    }


}