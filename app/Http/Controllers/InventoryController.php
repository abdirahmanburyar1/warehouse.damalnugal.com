<?php

namespace App\Http\Controllers;

use App\Http\Resources\InventoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Location;
use App\Models\Category;
use App\Models\Dosage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;
use Inertia\Inertia;
use App\Events\InventoryEvent;
use Illuminate\Support\Facades\Event;
use App\Models\IssueQuantityReport;
use App\Models\IssueQuantityItem;
use App\Models\WarehouseAmc;
use App\Events\InventoryUpdated;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UploadInventory;
use App\Services\InventoryAnalyticsService;
use App\Services\WarehouseAmcCalculationService;
use App\Models\InventoryItem;
use App\Models\Warehouse;

class InventoryController extends Controller
{
	public function index(Request $request)
	{
		// Increase execution time limit to prevent timeout
		set_time_limit(120);
		
		try {
			$inventoryItemColumns = ['id', 'product_id', 'warehouse_id', 'quantity', 'location', 'batch_number', 'expiry_date', 'uom', 'unit_cost', 'total_cost'];
			// Newer schema has `source`; older schema may not.
			if (Schema::hasColumn('inventory_items', 'source')) {
				$inventoryItemColumns[] = 'source';
			}

			// Base query with relationships - optimized
			$productQuery = Product::query()
				->with([
					'category:id,name',
					'dosage:id,name',
					'items' => function($query) use ($inventoryItemColumns) {
						$query->select($inventoryItemColumns)
							  ->with('warehouse:id,name');
					}
				]);
	
			// Apply filters
			if ($request->filled('search')) {
				$search = $request->search;
				$productQuery->where(function($q) use ($search) {
					$q->where('products.name', 'like', "%{$search}%")
					  ->orWhereHas('items', function($sub) use ($search) {
						  $sub->where(function($w) use ($search) {
							  $w->where('barcode', 'like', "%{$search}%")
								->orWhere('batch_number', 'like', "%{$search}%");
						  });
					  });
				});
			}
	
			if ($request->filled('category')) {
				$productQuery->whereHas('category', fn($q) => $q->where('name', $request->category));
			}
			if ($request->filled('dosage')) {
				$productQuery->whereHas('dosage', fn($q) => $q->where('name', $request->dosage));
			}
			if ($request->filled('location')) {
				$productQuery->whereHas('items', fn($q) => $q->where('location', $request->location));
			}
			if ($request->filled('warehouse')) {
				$productQuery->whereHas('items.warehouse', fn($q) => $q->where('name', $request->warehouse));
			}
	
			// Status filter - will be applied after data is loaded but before pagination
			$statusFilter = $request->filled('status') ? $request->status : null;
	
			// Get all products first (without pagination) if status filter is applied
			if ($statusFilter) {
				$allProducts = $productQuery->get();
			} else {
				// Paginate normally if no status filter
				$products = $productQuery->paginate(
					$request->input('per_page', 25),
					['*'],
					'page',
					$request->input('page', 1)
				)->withQueryString();
				$products->setPath(url()->current());
			}
			
			// Calculate AMC and reorder level using the warehouse AMC service
			$currentProducts = $statusFilter ? $allProducts : $products->getCollection();
			$productIds = $currentProducts->pluck('id')->toArray();
			$warehouseAmcService = new WarehouseAmcCalculationService();
			$amcResults = [];
			
			try {
				$amcResults = $warehouseAmcService->calculateAmcForProducts($productIds);
			} catch (\Exception $e) {
				Log::warning('Warehouse AMC calculation failed for inventory: ' . $e->getMessage());
			}
			
			$currentProducts->transform(function ($product) use ($amcResults) {
				$amc = isset($amcResults[$product->id]) ? $amcResults[$product->id]['amc'] : 0;
				
				// Calculate reorder level: AMC × 3 (simplified version without buffer stock)
				$reorderLevel = $amc > 0 ? round($amc * 3, 2) : 0;
				
				$product->reorder_level = $reorderLevel;
				$product->amc = $amc;
				return $product;
			});
			
			// Apply status filter after data is loaded and calculated
			if ($statusFilter) {
				try {
					$filteredCollection = $allProducts->filter(function ($product) use ($statusFilter) {
						try {
							$totalQuantity = $product->items->sum('quantity');
							$reorderLevel = $product->reorder_level ?? 0;
							
							
							switch ($statusFilter) {
								case 'in_stock':
									if ($reorderLevel <= 0) {
										return $totalQuantity > 0;
									}
									$lowStockThreshold = $reorderLevel * 1.3;
									return $totalQuantity > $lowStockThreshold;
									
								case 'low_stock':
									if ($reorderLevel <= 0) {
										// No reorder level set, cannot be "low stock" - return false
										return false;
									}
									$lowStockThreshold = $reorderLevel * 1.3;
									$isLowStock = $totalQuantity > $reorderLevel && $totalQuantity <= $lowStockThreshold;
									return $isLowStock;
									
								case 'low_stock_reorder_level':
									if ($reorderLevel <= 0) return false;
									return $totalQuantity > 0 && $totalQuantity <= $reorderLevel;
									
								case 'out_of_stock':
									return $totalQuantity == 0;
									
								default:
									return true;
							}
						} catch (\Exception $e) {
							Log::warning('[INVENTORY-FILTER] Error filtering product ' . ($product->id ?? 'unknown') . ': ' . $e->getMessage());
							return false; // Exclude problematic products
						}
					});
					
					// Create pagination from filtered results
					
					$perPage = $request->input('per_page', 25);
					$currentPage = $request->input('page', 1);
					$offset = ($currentPage - 1) * $perPage;
					$paginatedItems = $filteredCollection->slice($offset, $perPage)->values();
					
					$products = new \Illuminate\Pagination\LengthAwarePaginator(
						$paginatedItems,
						$filteredCollection->count(),
						$perPage,
						$currentPage,
						[
							'path' => request()->url(),
							'pageName' => 'page',
						]
					);
					$products->withQueryString();
				} catch (\Exception $e) {
					Log::error('[INVENTORY-FILTER] Error applying status filter: ' . $e->getMessage());
					// Continue without filtering if there's an error
				}
			}
	

	
			// Filters data
			$categories = Category::orderBy('name')->pluck('name')->toArray();
			$dosages = Dosage::orderBy('name')->pluck('name')->toArray();
			$locations = Location::orderBy('location')->pluck('location')->toArray();
			$warehouses = Warehouse::orderBy('name')->pluck('name')->toArray();
	
			// Calculate status counts independently of pagination
			$statusCounts = $this->calculateInventoryStatusCounts($request);
	
			return Inertia::render('Inventory/Index', [
				'inventories' => InventoryResource::collection($products),
				'inventoryStatusCounts' => $statusCounts,
				'filters' => $request->only(['search', 'per_page', 'page', 'category', 'dosage', 'status', 'location', 'warehouse']),
				'category' => $categories,
				'dosage' => $dosages,
				'locations' => $locations,
				'warehouses' => $warehouses,
			]);
	
		} catch (\Exception $e) {
			Log::error('[INVENTORY-ERROR] Error in index method: ' . $e->getMessage(), [
				'exception' => $e,
				'request' => $request->all()
			]);

			return back()->withErrors(['error' => 'An error occurred while loading inventory data.']);
		}
	}

	/**
	 * Calculate inventory status counts independently of pagination
	 */
	private function calculateInventoryStatusCounts($request)
	{
		try {
			$inventoryItemColumns = ['id', 'product_id', 'warehouse_id', 'quantity', 'location', 'batch_number', 'expiry_date', 'uom', 'unit_cost', 'total_cost'];
			// Newer schema has `source`; older schema may not.
			if (Schema::hasColumn('inventory_items', 'source')) {
				$inventoryItemColumns[] = 'source';
			}

			// Use the same query structure as the main index method
			$productQuery = Product::query()
				->with([
					'category:id,name',
					'dosage:id,name',
					'items' => function($query) use ($inventoryItemColumns) {
						$query->select($inventoryItemColumns)
							  ->with('warehouse:id,name');
					}
				]);

			// Apply search filter if provided
			if ($request->filled('search')) {
				$search = $request->search;
				$productQuery->where(function($q) use ($search) {
					$q->where('products.name', 'like', "%{$search}%")
					  ->orWhereHas('items', function($sub) use ($search) {
						  $sub->where(function($w) use ($search) {
							  $w->where('barcode', 'like', "%{$search}%")
								->orWhere('batch_number', 'like', "%{$search}%");
						  });
					  });
				});
			}

			// Apply category filter if provided
			if ($request->filled('category')) {
				$productQuery->whereHas('category', fn($q) => $q->where('name', $request->category));
			}

			// Apply dosage filter if provided
			if ($request->filled('dosage')) {
				$productQuery->whereHas('dosage', fn($q) => $q->where('name', $request->dosage));
			}

			// Apply location filter if provided
			if ($request->filled('location')) {
				$productQuery->whereHas('items', fn($q) => $q->where('location', $request->location));
			}

			// Apply warehouse filter if provided
			if ($request->filled('warehouse')) {
				$productQuery->whereHas('items.warehouse', fn($q) => $q->where('name', $request->warehouse));
			}

			// Get all products that match the filters (no pagination)
			$allProducts = $productQuery->get();

			// Calculate AMC and reorder level for status counting (same as main query)
			$allProductIds = $allProducts->pluck('id')->toArray();
			$warehouseAmcService = new WarehouseAmcCalculationService();
			$allAmcResults = [];
			
			try {
				$allAmcResults = $warehouseAmcService->calculateAmcForProducts($allProductIds);
			} catch (\Exception $e) {
				Log::warning('Warehouse AMC calculation failed for status counting: ' . $e->getMessage());
			}
			
			$allProducts->transform(function ($product) use ($allAmcResults) {
				$amc = isset($allAmcResults[$product->id]) ? $allAmcResults[$product->id]['amc'] : 0;
				$reorderLevel = $amc > 0 ? round($amc * 3, 2) : 0;
				
				$product->reorder_level = $reorderLevel;
				$product->amc = $amc;
				return $product;
			});

			// Initialize status counts
			$statusCounts = [
				[ 'status' => 'in_stock', 'count' => 0 ],
				[ 'status' => 'low_stock', 'count' => 0 ],
				[ 'status' => 'low_stock_reorder_level', 'count' => 0 ],
				[ 'status' => 'out_of_stock', 'count' => 0 ],
			];

			// Calculate status counts for all products using the same logic as main query
			foreach ($allProducts as $product) {
				try {
					$totalQuantity = $product->items->sum('quantity');
					$reorderLevel = $product->reorder_level ?? 0;

					if ($totalQuantity <= 0) {
						$statusCounts[3]['count']++; // out_of_stock
					} elseif ($reorderLevel > 0) {
						$lowStockThreshold = $reorderLevel * 1.3;
						
						if ($totalQuantity > $lowStockThreshold) {
							$statusCounts[0]['count']++; // in_stock
						} elseif ($totalQuantity > $reorderLevel && $totalQuantity <= $lowStockThreshold) {
							$statusCounts[1]['count']++; // low_stock
						} else {
							$statusCounts[2]['count']++; // low_stock_reorder_level
						}
					} else {
						// No reorder level set - cannot be "low stock", only in_stock or out_of_stock
						if ($totalQuantity > 0) {
							$statusCounts[0]['count']++; // in_stock
						} else {
							$statusCounts[3]['count']++; // out_of_stock
						}
					}
				} catch (\Exception $e) {
					Log::warning('[INVENTORY-STATS] Error calculating status for product ' . ($product->id ?? 'unknown') . ': ' . $e->getMessage());
					// Skip problematic products in counting
				}
			}

			return $statusCounts;

		} catch (\Exception $e) {
			Log::error('[INVENTORY-STATS] Error calculating inventory status counts: ' . $e->getMessage());
			
			// Return empty counts if there's an error
			return [
				[ 'status' => 'in_stock', 'count' => 0 ],
				[ 'status' => 'low_stock', 'count' => 0 ],
				[ 'status' => 'low_stock_reorder_level', 'count' => 0 ],
				[ 'status' => 'out_of_stock', 'count' => 0 ],
			];
		}
	}

	/**
	 * Apply status filter to the product query
	 */
	protected function applyStatusFilter($query, $status)
	{
		return $query->whereHas('inventories.items', function($subQuery) use ($status) {
			$subQuery->where('inventory_items.quantity', '>', 0);
		});
	}

	/**
	 * Store a newly created resource in storage.
	 */
	public function store(Request $request)
	{
		try {
		   
		$validated = $request->validate([
			'id' => 'nullable|exists:inventories,id',
			'product_id' => 'required|exists:products,id',
			'warehouse_id' => 'required|exists:warehouses,id',
			'quantity' => 'required|numeric|min:0',
			'manufacturing_date' => 'nullable|date',
			'expiry_date' => 'nullable|date|after:manufacturing_date',
			'batch_number' => 'nullable|string',
			'location' => 'nullable|string',
			'notes' => 'nullable|string',
			'is_active' => 'boolean',
		]);

		$isNew = !$request->id;
		
		$inventory = Inventory::updateOrCreate(
			['id' => $request->id],
			$validated
		);

		// event(new InventoryUpdated());
		
		return response()->json( $request->id ? 'Inventory updated successfully' : 'Inventory created successfully', 200);
		} catch (\Throwable $th) {
			logger()->error('[PUSHER-DEBUG] Error in store method: ' . $th->getMessage());
			return response()->json($th->getMessage(), 500);
		}
	}

	/**
	 * Display the specified resource.
	 */
	public function show(Inventory $inventory)
	{
		$inventory->load(['product.category', 'product.dosage']);
		return response()->json([
			'success' => true,
			'data' => new InventoryResource($inventory),
		]);
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(Inventory $inventory)
	{        
		try {
			$inventory->delete();
			event(new InventoryEvent());
			Log::info('Successfully dispatched InventoryEvent for deleted inventory ID: ' . $inventory->id);
			return response()->json([
				'success' => true,
				'message' => 'Inventory item deleted successfully',
			], 200);
		} catch (\Throwable $th) {
			return response()->json($th->getMessage(), 500);
		}   
	}
	
	public function getLocations(Request $request){
		try {
			$warehouse = $request->input('warehouse');
			
			if (!$warehouse) {
				return response()->json([], 200);
			}

			$locations = Location::where('warehouse', $warehouse)
				->select('id', 'location', 'warehouse')
				->get()
				->map(function($location) {
					return [
						'id' => $location->id,
						'location' => $location->location,
						'warehouse' => $location->warehouse
					];
				});

			return response()->json($locations, 200);
		} catch (\Throwable $th) {
			return response()->json($th->getMessage(), 500);
		}
	}

	public function updateLocation(Request $request)
	{
		$request->validate([
			'inventory_item_id' => 'required|exists:inventory_items,id',
			'location' => 'required|string|max:255'
		]);

		try {
			$inventoryItem = InventoryItem::findOrFail($request->inventory_item_id);
			$inventoryItem->update([
				'location' => $request->location
			]);

			// Trigger inventory update event
			// event(new InventoryUpdated($inventoryItem->inventory));

			return response()->json([
				'success' => true,
				'message' => 'Location updated successfully',
				'data' => $inventoryItem
			]);
		} catch (\Exception $e) {
			Log::error('Error updating inventory location: ' . $e->getMessage());
			
			return response()->json([
				'success' => false,
				'message' => 'Failed to update location: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Import inventory items from Excel file
	 */
	public function import(Request $request)
	{
		// Server-side permission enforcement (DB permissions are dash-based)
		$user = auth()->user();
		if (!$user || (!$user->can('inventory-manage') && !$user->can('manage-system') && !$user->isAdmin())) {
			return response()->json([
				'success' => false,
				'message' => 'You do not have permission to import inventory'
			], 403);
		}

		try {
			if (!$request->hasFile('file')) {
				return response()->json([
					'success' => false,
					'message' => 'No file was uploaded'
				], 422);
			}
		
			$file = $request->file('file');
		
			// Validate file type
			$extension = $file->getClientOriginalExtension();
			if (!$file->isValid() || !in_array($extension, ['xlsx', 'xls', 'csv'])) {
				return response()->json([
					'success' => false,
					'message' => 'Invalid file type. Please upload an Excel file (.xlsx, .xls) or CSV file'
				], 422);
			}
		
			// Validate file size (max 50MB)
			if ($file->getSize() > 50 * 1024 * 1024) {
				return response()->json([
					'success' => false,
					'message' => 'File size too large. Maximum allowed size is 50MB'
				], 422);
			}

			$result = DB::transaction(function () use ($file, $extension) {
				Log::info('Starting synchronous inventory import with Maatwebsite Excel', [
					'original_name' => $file->getClientOriginalName(),
					'file_size' => $file->getSize(),
					'extension' => $extension
				]);

				$import = new UploadInventory();
				Excel::import($import, $file);

				if ($import->createdCount === 0) {
					$sampleText = count($import->missingProductsSample) ? implode(', ', $import->missingProductsSample) : '';
					$message = 'No inventory items were imported.';
					if ($import->missingProductRows > 0) {
						$message .= ' Some rows were skipped because the product does not exist in the system.';
						if ($sampleText !== '') {
							$message .= " Missing (sample): {$sampleText}";
						}
					}
					throw new \Exception($message, 422);
				}

				$warning = null;
				if ($import->missingProductRows > 0) {
					$sampleText = count($import->missingProductsSample) ? implode(', ', $import->missingProductsSample) : '';
					$warning = 'Some rows were skipped because the product does not exist in the system.';
					if ($sampleText !== '') {
						$warning .= " Missing (sample): {$sampleText}";
					}
				}

				return [
					'created_count' => $import->createdCount,
					'warning' => $warning,
				];
			});

			return response()->json([
				'success' => true,
				'message' => "Imported {$result['created_count']} inventory item(s) successfully.",
				'created_count' => $result['created_count'],
				'warning' => $result['warning'],
			]);
		
		} catch (\Exception $e) {
			Log::error('Product import failed', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);

			if ((int) $e->getCode() === 422) {
				return response()->json([
					'success' => false,
					'message' => $e->getMessage(),
				], 422);
			}
		
			return response()->json([
				'success' => false,
				'message' => 'Import failed: ' . $e->getMessage()
			], 500);
		}
	}


	/**
	 * Check if an item needs reorder action (out of stock)
	 *
	 * @param mixed $item
	 * @return bool
	 */
	private function needsReorderAction($item): bool
	{
		// If item doesn't exist or has zero quantity, it needs reorder
		if (!$item || !isset($item->quantity) || (float) $item->quantity <= 0) {
			return true;
		}
		return false;
	}
}
