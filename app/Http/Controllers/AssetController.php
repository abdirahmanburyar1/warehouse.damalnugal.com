<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetItem;
use App\Models\SubLocation;
use App\Models\AssetLocation;
use App\Models\Region;
use App\Models\User;
use App\Models\Assignee;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\AssetHistory;
use App\Models\FundSource;
use App\Models\Facility;
use App\Http\Resources\AssetResource;
use App\Http\Resources\AssetItemResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AssetsImport;
use App\Notifications\AssetActionRequired;
use Inertia\Inertia;
use Illuminate\Support\Carbon;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $assetItems = AssetItem::query();

        // Organization filter - only show asset items from the same organization if user has one
        if (auth()->check() && auth()->user() && !empty(auth()->user()->organization)) {
            $assetItems->whereHas('asset', function($query) {
                $query->where('organization', auth()->user()->organization);
            });
        }
        
        if($request->filled('search')){
            $assetItems->where(function($query) use ($request) {
                $query->whereLike('asset_tag', '%'.$request->search.'%')   
                    ->orWhereLike('asset_name', '%'.$request->search.'%')
                ->orWhereLike('serial_number', '%'.$request->search.'%')
                    ->orWhereHas('asset.fundSource', function($q) use ($request){
                        $q->where('name', 'like', '%'.$request->search.'%');
                    });
                });
        }

        if($request->filled('region_id')){
            $assetItems->whereHas('asset', function($query) use ($request) {
                $query->where('region_id', $request->region_id);
            });
        }

        if($request->filled('location_id')){
            $assetItems->whereHas('asset', function($query) use ($request) {
                $query->where('asset_location_id', $request->location_id);
            });
        }

        if($request->filled('sub_location_ids') && is_array($request->sub_location_ids)){
            $assetItems->whereHas('asset', function($query) use ($request) {
                $query->whereIn('sub_location_id', $request->sub_location_ids);
            });
        } elseif($request->filled('sub_location_id')){
            $assetItems->whereHas('asset', function($query) use ($request) {
                $query->where('sub_location_id', $request->sub_location_id);
            });
        }

        if($request->filled('fund_source_id')){
            $assetItems->whereHas('asset', function($query) use ($request) {
                $query->where('fund_source_id', $request->fund_source_id);
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $assetItems->where('status', $request->status);
        }

        // Depreciation filter
        if ($request->filled('depreciation_filter')) {
            if ($request->depreciation_filter === 'with_depreciation') {
                $assetItems->whereHas('depreciation');
            } elseif ($request->depreciation_filter === 'without_depreciation') {
                $assetItems->whereDoesntHave('depreciation');
            }
        }

        // New filters
        if ($request->filled('category_id')) {
            $assetItems->where('asset_category_id', $request->category_id);
        }
        if ($request->filled('type_id')) {
            $assetItems->where('asset_type_id', $request->type_id);
        }
        if ($request->filled('assignee_id')) {
            $assetItems->where('assignee_id', $request->input('assignee_id'));
        }
        if ($request->filled('acquisition_from') || $request->filled('acquisition_to')) {
            $from = $request->input('acquisition_from');
            $to = $request->input('acquisition_to');
            if ($from && $to) {
                $assetItems->whereHas('asset', function($query) use ($from, $to) {
                    $query->whereBetween('acquisition_date', [$from, $to]);
                });
            } elseif ($from) {
                $assetItems->whereHas('asset', function($query) use ($from) {
                    $query->whereDate('acquisition_date', '>=', $from);
                });
            } elseif ($to) {
                $assetItems->whereHas('asset', function($query) use ($to) {
                    $query->whereDate('acquisition_date', '<=', $to);
                });
            }
        }
        if ($request->filled('created_from') || $request->filled('created_to')) {
            $from = $request->input('created_from');
            $to = $request->input('created_to');
            if ($from && $to) {
                $assetItems->whereBetween('created_at', [$from, $to]);
            } elseif ($from) {
                $assetItems->whereDate('created_at', '>=', $from);
            } elseif ($to) {
                $assetItems->whereDate('created_at', '<=', $to);
            }
        }
    
        $assetItems->orderBy('created_at', 'desc');

        $assetItems = $assetItems->with([
            'asset:id,asset_number,acquisition_date,fund_source_id,region_id,asset_location_id,sub_location_id',
            'asset.fundSource:id,name',
            'asset.region:id,name',
            'asset.assetLocation:id,name',
            'asset.subLocation:id,name',
            'category:id,name',
            'type:id,name',
            'assignee:id,name',
            'depreciation:id,asset_item_id,current_value,accumulated_depreciation,depreciation_method,depreciation_start_date'
        ])
            ->paginate($request->input('per_page', 10), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();

        $assetItems->setPath(url()->current()); // Force Laravel to use full URLs

        $count = AssetItem::when(auth()->check() && auth()->user() && !empty(auth()->user()->organization), function($query) {
            $query->whereHas('asset', function($q) {
                $q->where('organization', auth()->user()->organization);
            });
        })->count();

        $locations = AssetLocation::with('subLocations')->get();
        $categories = AssetCategory::select('id','name')->get();
        $types = AssetType::select('id','name','asset_category_id')->get();
        $assignees = Assignee::select('id','name')->get();
        
        return inertia('Assets/Index', [
            'locations' => $locations,
            'assets' => AssetItemResource::collection($assetItems),
            'filters' => $request->only('page','per_page','search','region_id','location_id','sub_location_id','fund_source_id','category_id','type_id','assignee_id','acquisition_from','acquisition_to','created_from','created_to','status','depreciation_filter'),
            'assetsCount' => $count,
            'regions' => Region::get(),
            'fundSources' => FundSource::get(),
            'categories' => $categories,
            'types' => $types,
            'assignees' => $assignees,
        ]);
    }

    public function create()
    {
        // Check if user has permission to create assets
        if (!auth()->user()->hasPermission('asset-create') && !auth()->user()->isAdmin()) {
            return redirect()->route('assets.index')->with('error', 'You do not have permission to create assets.');
        }

        // Allow creating assets even without organization
        // This allows admins to create assets and assign organizations
        
        $locations = AssetLocation::all();
        $categories = AssetCategory::all();
        $fundSources = FundSource::get();
        $regions = Region::get();
        $types = AssetType::all();
        $users = User::select('id','name','email')->get();
        $assignees = Assignee::select('id','name')->orderBy('name')->get();
        $facilities = Facility::orderBy('name')->get(['id', 'name', 'district', 'region']);
        
        return Inertia::render('Assets/Create', [
            'locations' => $locations,
            'categories' => $categories,
            'fundSources' => $fundSources,
            'regions' => $regions,
            'types' => $types,
            'users' => $users,
            'assignees' => $assignees,
            'facilities' => $facilities,
        ]);
    }

    public function store(Request $request)
    {
        // Check if user has permission to create assets
        if (!auth()->user()->hasPermission('asset-create') && !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create assets.'
            ], 403);
        }

        try {
            return DB::transaction(function() use ($request){
                // Validate the main asset data
                $validatedAsset = $request->validate([
                    'asset_number' => 'nullable|string|unique:assets,asset_number',
                    'acquisition_date' => 'required|date',
                    'fund_source_id' => 'required|exists:fund_sources,id',
                    'region_id' => 'required|exists:regions,id',
                    'asset_location_id' => 'required|exists:asset_locations,id',
                    'sub_location_id' => 'nullable|exists:sub_locations,id',
                    'facility_id' => 'nullable|exists:facilities,id',
                ]);

                // Validate asset items array
                $request->validate([
                    'asset_items' => 'required|array|min:1',
                    'asset_items.*.asset_tag' => 'required|string|max:255',
                    'asset_items.*.asset_name' => 'required|string|max:255',
                    'asset_items.*.serial_number' => 'required|string|max:255',
                    'asset_items.*.asset_category_id' => 'required|exists:asset_categories,id',
                    'asset_items.*.asset_type_id' => 'required|exists:asset_types,id',
                    'asset_items.*.assignee_id' => 'nullable|exists:assignees,id',
                    'asset_items.*.original_value' => 'required|numeric|min:0',
                ]);

                // Get asset items data directly
                $assetItemsData = $request->asset_items;
                
                // Generate asset number if not provided
                if (empty($validatedAsset['asset_number'])) {
                    $validatedAsset['asset_number'] = $this->generateAssetNumber();
                }

                // Set default status for the asset
                $validatedAsset['status'] = 'pending_approval';
                $validatedAsset['submitted_by'] = auth()->id();
                $validatedAsset['submitted_at'] = now();
                
                // Handle nullable sub_location_id
                if (empty($validatedAsset['sub_location_id'])) {
                    $validatedAsset['sub_location_id'] = null;
                }

                // Handle nullable facility_id
                if (empty($validatedAsset['facility_id'])) {
                    $validatedAsset['facility_id'] = null;
                }

                // Create the main asset record
                $asset = Asset::create($validatedAsset);

                // Create asset items
                $assetItems = [];
                foreach ($assetItemsData as $itemData) {
                    $assetItem = AssetItem::create([
                        'asset_id' => $asset->id,
                        'asset_tag' => $itemData['asset_tag'],
                        'asset_name' => $itemData['asset_name'],
                        'serial_number' => $itemData['serial_number'],
                        'asset_category_id' => $itemData['asset_category_id'],
                        'asset_type_id' => $itemData['asset_type_id'],
                        'assignee_id' => $itemData['assignee_id'],
                        'status' => 'pending_approval', // Default status for new items
                        'original_value' => $itemData['original_value'],
                    ]);

                    $assetItems[] = $assetItem;
                }

                // Create asset history record for creation
                $asset->createHistory([
                    'action' => 'asset_created',
                    'action_type' => 'creation',
                    'notes' => 'Asset created with ' . count($assetItems) . ' items',
                    'performed_by' => auth()->id(),
                    'performed_at' => now(),
                ]);

                // Create history for each asset item
                foreach ($assetItems as $item) {
                    $item->createHistory([
                        'action' => 'item_created',
                        'action_type' => 'creation',
                        'notes' => 'Asset item created',
                        'performed_by' => auth()->id(),
                        'performed_at' => now(),
                    ]);
                }

                // Notify users with asset-review permission (workflow: next action is review)
                User::withPermission('asset-review')
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->where('id', '!=', auth()->id())
                    ->get()
                    ->each(fn ($u) => $u->notify(new AssetActionRequired($asset, AssetActionRequired::ACTION_NEEDS_REVIEW)));

                return response()->json([
                    'success' => true,
                    'message' => 'Asset created successfully with ' . count($assetItems) . ' items. Pending approval.',
                    'asset' => $asset->load('assetItems'),
                    'redirect_url' => route('assets.index')
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create asset: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Generate a unique asset number
     */
    private function generateAssetNumber()
    {
        $prefix = 'AST';
        $year = date('Y');
        $month = date('m');
        
        // Get the last asset number for this month
        $lastAsset = Asset::where('asset_number', 'like', $prefix . $year . $month . '%')
            ->orderBy('asset_number', 'desc')
            ->first();
        
        if ($lastAsset) {
            // Extract the sequence number and increment
            $lastSequence = (int) substr($lastAsset->asset_number, -4);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }
        
        return $prefix . $year . $month . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }

    public function edit(Asset $asset)
    {
        // Load the asset with its actual relationships, filtered by organization through asset items
        $asset = Asset::with(['region', 'assetLocation', 'subLocation', 'fundSource', 'facility'])
            ->whereHas('assetItems', function($query) {
                if (auth()->check() && auth()->user() && !empty(auth()->user()->organization)) {
                    $query->where('organization', auth()->user()->organization);
                }
            })
            ->findOrFail($asset->id);
            
        // Get the first asset item for additional details
        $assetItem = $asset->assetItems()->with(['category', 'type', 'assignee'])->first();
        
        $locations = AssetLocation::orderBy('name')->get();
        $categories = AssetCategory::orderBy('name')->get();
        $fundSources = FundSource::orderBy('name')->get();
        $regions = Region::orderBy('name')->get();
        $types = AssetType::orderBy('name')->get();
        $assignees = Assignee::select('id','name')->orderBy('name')->get();
        $facilities = Facility::orderBy('name')->get(['id', 'name', 'district', 'region']);
        
        return Inertia::render('Assets/Edit', [
            'asset' => $asset,
            'assetItem' => $assetItem,
            'locations' => $locations,
            'categories' => $categories,
            'fundSources' => $fundSources,
            'regions' => $regions,
            'types' => $types,
            'assignees' => $assignees,
            'facilities' => $facilities,
        ]);
    }

    public function update(Request $request, Asset $asset)
    {
        try {
            // Validate asset-level fields
            $assetValidated = $request->validate([
                'region_id' => 'required|exists:regions,id',
                'asset_location_id' => 'required|exists:asset_locations,id',
                'sub_location_id' => 'required|exists:sub_locations,id',
                'facility_id' => 'nullable|exists:facilities,id',
                'fund_source_id' => 'required|exists:fund_sources,id',
                'acquisition_date' => 'required|date',
            ]);
            if (empty($assetValidated['facility_id'])) {
                $assetValidated['facility_id'] = null;
            }

            // Update the asset
            $asset->update($assetValidated);

            // Validate and update asset item fields if provided
            if ($request->has('asset_item_data')) {
                $assetItemValidated = $request->validate([
                    'asset_item_data.asset_tag' => 'required|string|max:255',
                    'asset_item_data.asset_category_id' => 'required|exists:asset_categories,id',
                    'asset_item_data.asset_type_id' => 'required|exists:asset_types,id',
                    'asset_item_data.asset_name' => 'required|string|max:255',
                    'asset_item_data.serial_number' => 'required|string|max:255',
                    'asset_item_data.original_value' => 'required|numeric|min:0',
                    'asset_item_data.status' => 'required|string|in:pending_approval,in_use,maintenance,retired,disposed,functioning,not_functioning',
                    'asset_item_data.assignee_id' => 'nullable|exists:assignees,id',
                ]);

                // Update the first asset item
                $assetItem = $asset->assetItems()->first();
                if ($assetItem) {
                    $assetItem->update($assetItemValidated['asset_item_data']);
                }
            }

            return response()->json('Updated', 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();
        return response()->json('Deleted', 200);
    }

    /**
     * Display the asset locations index page.
     */
    public function locationIndex()
    {
        $locations = AssetLocation::all();
        return Inertia::render('Assets/Locations/Index', [
            'locations' => $locations
        ]);
    }
    
    /**
     * Display the asset sub-locations index page.
     */
    public function subLocationIndex()
    {
        $subLocations = SubLocation::with('location')->get();
        $locations = AssetLocation::all();
        return Inertia::render('Assets/SubLocations/Index', [
            'subLocations' => $subLocations,
            'locations' => $locations
        ]);
    }

    public function getSubLocations($locationId)
    {
        $subLocations = SubLocation::where('asset_location_id', $locationId)->get();
        return response()->json($subLocations);
    }

    /**
     * Store a new sub-location.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeSubLocation(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'asset_location_id' => 'required|exists:asset_locations,id'
            ]);
            
            $subLocation = SubLocation::create([
                'name' => $request->name,
                'asset_location_id' => $request->asset_location_id
            ]);
            
            return response()->json($subLocation, 201);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Store a new asset category.
     */
    public function storeCategory(Request $request){
        try {
            $request->validate([
                'name' => 'required',
            ]);
            
            $category = AssetCategory::create([
                'name' => $request->name
            ]);
            
            return response()->json($category, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Store a new asset location.
     */
    public function storeAssetLocation(Request $request){
        try {
            $request->validate([
                'name' => 'required',
            ]);
            $location = AssetLocation::create([
                'name' => $request->name
            ]);
            return response()->json($location, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Store a new fund source.
     */
    public function storeFundSource(Request $request){
        try {
            $request->validate([
                'name' => 'required',
            ]);
            $fundSource = FundSource::create([
                'name' => $request->name
            ]);
            return response()->json($fundSource, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Store a new region.
     */
    public function storeRegion(Request $request){
        // Allow creating regions even without organization
        // This allows admins to create regions
        
        try {
            $request->validate([
                'name' => 'required|unique:regions',
            ],[
                'name.unique' => $request->name . " already exists",
            ]);
            
            $region = Region::create([
                'name' => $request->name
            ]);
            
            return response()->json($region->name, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Display the approvals index page
     */
    public function approvalsIndex(Request $request)
    {
        // Load all assets that have been submitted (including approved ones)
        // This allows us to show the full approval workflow history
        $assets = Asset::whereNotNull('submitted_at')
            ->whereHas('assetItems', function($query) {
                if (auth()->check() && auth()->user() && !empty(auth()->user()->organization)) {
                    $query->where('organization', auth()->user()->organization);
                }
            });
        
        $assets = $assets
                      ->with(['region', 'assetLocation', 'subLocation'])
                      ->get(['id', 'asset_number', 'acquisition_date'])
                      ->map(function($asset) {
                          return [
                              'id' => $asset->id,
                              'asset_number' => $asset->asset_number,
                              'acquisition_date' => $asset->acquisition_date,
                              'region_name' => $asset->region?->name,
                              'location_name' => $asset->assetLocation?->name,
                              'sub_location_name' => $asset->subLocation?->name,
                          ];
                      })
                      ->toArray();

        $assetItem = null;
        if ($request->selectedAsset) {
            $assetItem = Asset::where('asset_number', $request->selectedAsset)
                ->whereHas('assetItems', function($query) {
                    if (auth()->check() && auth()->user() && !empty(auth()->user()->organization)) {
                        $query->where('organization', auth()->user()->organization);
                    }
                })
                ->with([
                    'assetItems.category', 
                    'assetItems.type', 
                    'assetItems.assignee', 
                    'fundSource', 
                    'region', 
                    'assetLocation', 
                    'subLocation',
                    'submittedBy',
                    'reviewedBy',
                    'approvedBy',
                    'rejectedBy'
                ])
                ->first();
        }

        return Inertia::render('Assets/Show', [
            'assets' => $assets,
            'assetItem' => $assetItem,
            'filters' => $request->only('selectedAsset'),
        ]);
    }

    /**
     * Approve an asset (simple approval for new structure)
     */
    public function approve(Asset $asset)
    {
        $user = auth()->user();
        if (!$user->hasPermission('asset-approve') && !$user->hasPermission('asset-manage') && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to approve assets.'], 403);
        }
        try {
            // Check if user has access to this asset through organization
            if (auth()->check() && auth()->user() && !empty(auth()->user()->organization)) {
                $hasAccess = $asset->organization === auth()->user()->organization;
                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have access to this asset'
                    ], 403);
                }
            }

            // Check if asset is in reviewed status (has reviewed_at but no approved_at)
            if (!$asset->reviewed_at || $asset->approved_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset must be reviewed before approval'
                ], 400);
            }

            $asset->update([
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Update all asset items to approved status
            $asset->assetItems()->update([
                'status' => 'in_use'
            ]);

            // Create history records
            $asset->createHistory([
                'action' => 'asset_approved',
                'action_type' => 'approval',
                'notes' => 'Asset approved by ' . auth()->user()->name,
                'performed_by' => auth()->id(),
                'performed_at' => now(),
            ]);

            foreach ($asset->assetItems as $item) {
                $item->createHistory([
                    'action' => 'item_approved',
                    'action_type' => 'approval',
                    'notes' => 'Asset item approved',
                    'performed_by' => auth()->id(),
                    'performed_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Asset approved successfully',
                'asset' => $asset->fresh(['approvedBy', 'assetItems'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve asset: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject an asset (simple rejection for new structure)
     */
    public function reject(Request $request, Asset $asset)
    {
        $user = auth()->user();
        if (!$user->hasPermission('asset-approve') && !$user->hasPermission('asset-reject') && !$user->hasPermission('asset-manage') && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to reject assets.'], 403);
        }
        try {
            // Check if user has access to this asset through organization
            if (auth()->check() && auth()->user() && !empty(auth()->user()->organization)) {
                $hasAccess = $asset->organization === auth()->user()->organization;
                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have access to this asset'
                    ], 403);
                }
            }

            // Check if asset is in reviewed status (has reviewed_at but no approved_at)
            if (!$asset->reviewed_at || $asset->approved_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset must be reviewed before rejection'
                ], 400);
            }

            $request->validate([
                'rejection_reason' => 'required|string|max:500'
            ]);

            $asset->update([
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            // Update all asset items to rejected status
            $asset->assetItems()->update([
                'status' => 'pending_approval'
            ]);

            // Create history records
            $asset->createHistory([
                'action' => 'asset_rejected',
                'action_type' => 'rejection',
                'notes' => 'Asset rejected: ' . $request->rejection_reason,
                'performed_by' => auth()->id(),
                'performed_at' => now(),
            ]);

            foreach ($asset->assetItems as $item) {
                $item->createHistory([
                    'action' => 'item_rejected',
                    'action_type' => 'rejection',
                    'notes' => 'Asset item rejected',
                    'performed_by' => auth()->id(),
                    'performed_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Asset rejected successfully',
                'asset' => $asset->fresh(['rejectedBy', 'assetItems'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject asset: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Review an asset (mark as reviewed)
     */
    public function review(Asset $asset)
    {
        $user = auth()->user();
        if (!$user->hasPermission('asset-review') && !$user->hasPermission('asset-manage') && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to review assets.'], 403);
        }
        try {
            // Check if user has access to this asset through organization
            if (auth()->check() && auth()->user() && !empty(auth()->user()->organization)) {
                $hasAccess = $asset->organization === auth()->user()->organization;
                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have access to this asset'
                    ], 403);
                }
            }

            // Check if asset is submitted for approval (has submitted_at but no reviewed_at)
            if (!$asset->submitted_at || $asset->reviewed_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset must be submitted for approval before review'
                ], 400);
            }

            $asset->update([
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            // Create history record
            $asset->createHistory([
                'action' => 'asset_reviewed',
                'action_type' => 'review',
                'notes' => 'Asset reviewed by ' . auth()->user()->name,
                'performed_by' => auth()->id(),
                'performed_at' => now(),
            ]);

            // Notify users with asset-approve or asset-reject (workflow: next action is approve/reject)
            $recipients = User::withPermission('asset-approve')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', $user->id)
                ->get()
                ->merge(
                    User::withPermission('asset-reject')
                        ->where('is_active', true)
                        ->whereNotNull('email')
                        ->where('id', '!=', $user->id)
                        ->get()
                )
                ->unique('id');
            $recipients->each(fn ($u) => $u->notify(new AssetActionRequired($asset->fresh(), AssetActionRequired::ACTION_READY_FOR_APPROVAL)));

            return response()->json([
                'success' => true,
                'message' => 'Asset reviewed successfully',
                'asset' => $asset->fresh(['reviewedBy'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to review asset: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a rejected asset back to pending approval
     */
    public function restore(Asset $asset)
    {
        $user = auth()->user();
        if (!$user->hasPermission('asset-approve') && !$user->hasPermission('asset-manage') && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to restore assets.'], 403);
        }
        try {
            // Check if asset is rejected (has rejected_at)
            if (!$asset->rejected_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset is not rejected'
                ], 400);
            }

            $asset->update([
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            // Create history record
            $asset->createHistory([
                'action' => 'asset_restored',
                'action_type' => 'restore',
                'notes' => 'Asset restored by ' . auth()->user()->name,
                'performed_by' => auth()->id(),
                'performed_at' => now(),
            ]);

            // Notify reviewers that asset is back in workflow and needs review
            User::withPermission('asset-review')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get()
                ->each(fn ($u) => $u->notify(new AssetActionRequired($asset->fresh(), AssetActionRequired::ACTION_NEEDS_REVIEW)));

            return response()->json([
                'success' => true,
                'message' => 'Asset restored successfully',
                'asset' => $asset->fresh(['assetItems'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore asset: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk approve multiple assets
     */
    public function bulkApprove(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasPermission('asset-approve') && !$user->hasPermission('asset-manage') && !$user->isAdmin()) {
            return back()->withErrors(['error' => 'You do not have permission to approve assets.']);
        }
        try {
            $request->validate([
                'asset_ids' => 'required|array',
                'asset_ids.*' => 'exists:assets,id'
            ]);

            $approvedCount = 0;
            foreach ($request->asset_ids as $assetId) {
                $asset = Asset::where('organization', auth()->user()->organization)->find($assetId);
                if ($asset && $asset->status === 'pending_approval') {
                    $asset->update([
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);

                    $asset->assetItems()->update([
                        'status' => 'in_use'
                    ]);

                    $approvedCount++;
                }
            }

            return back()->with('success', "{$approvedCount} assets approved successfully");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to bulk approve assets: ' . $e->getMessage()]);
        }
    }

    /**
     * Store a new assignee.
     */
    public function storeAssignee(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:50',
                'department' => 'nullable|string|max:100',
            ]);

            $assignee = Assignee::create($request->all());
            return response()->json($assignee, 201);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Transfer an asset to a new assignee.
     */
    public function transferAsset(Request $request, Asset $asset)
    {
        try {
            // Check if asset exists and is accessible
            if (!$asset) {
                return response()->json(['error' => 'Asset not found'], 404);
            }

            $request->validate([
                'assignee_id' => 'required|exists:assignees,id',
                'transfer_date' => 'required|date',
                'assignment_notes' => 'nullable|string',
                'update_asset_location' => 'nullable|boolean',
                'region_id' => 'nullable|exists:regions,id',
                'asset_location_id' => 'nullable|exists:asset_locations,id',
                'sub_location_id' => 'nullable|exists:sub_locations,id',
            ]);

            // For asset transfers, we typically don't want to change the asset's location
            // as it affects all asset items under that asset. Instead, we only update
            // the specific asset item's assignee and status.
            
            // Only update asset location if explicitly requested (for bulk transfers)
            if ($request->has('update_asset_location') && $request->update_asset_location) {                
                $asset->update([
                    'region_id' => $request->region_id,
                    'asset_location_id' => $request->asset_location_id,
                    'sub_location_id' => $request->sub_location_id,
                ]);
            }


            // Update only the specific asset item that was transferred
            // Since we're transferring a specific asset item, we need to find it
            $assetItems = $asset->assetItems()->get();
            
            $assetItem = $assetItems->first();
            
            if ($assetItem) {
                $assetItem->update([
                    'assignee_id' => $request->assignee_id,
                    'status' => 'in_use',
                ]);
            }


            // Create transfer history record for the specific asset item
            if ($assetItem) {
                $assetItem->createHistory([
                    'action' => 'asset_transferred',
                    'action_type' => 'transfer',
                    'notes' => 'Asset transferred to ' . $request->assignee_name . ' on ' . $request->transfer_date . 
                               ($request->assignment_notes ? ' - Notes: ' . $request->assignment_notes : ''),
                    'performed_by' => auth()->id(),
                    'performed_at' => now(),
                    'assignee_id' => $request->assignee_id,
                ]);
            }

            return response()->json([
                'message' => 'Asset transferred successfully',
                'asset' => $asset->fresh(['assetItems.assignee', 'region', 'assetLocation', 'subLocation'])
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Transfer failed: ' . $th->getMessage()], 500);
        }
    }


    /**
     * Show the details of an asset.
     */
    public function show(Request $request, $asset)
    {
        try {
            // Get the asset with its relationships, filtered by organization through asset items
            $assetWithRelations = Asset::with([
                'documents',
                'assetItems.assetHistory.performer',
                'region',
                'assetLocation',
                'subLocation',
                'fundSource',
                'submittedBy',
                'reviewedBy',
                'approvedBy',
                'rejectedBy',
                'assetItems.assignee',
                'assetItems.category',
                'assetItems.type'
            ])
            ->whereHas('assetItems', function($query) {
                if (auth()->check() && auth()->user() && !empty(auth()->user()->organization)) {
                    $query->where('organization', auth()->user()->organization);
                }
            })
            ->where(function($query) use ($asset) {
                // Try to match by ID first (if numeric), then by asset_tag
                if (is_numeric($asset)) {
                    $query->where('id', $asset);
                } else {
                    $query->where('asset_tag', $asset);
                }
            })
            ->first();

            if (!$assetWithRelations) {
                return back()->withErrors(['error' => 'Asset not found or you do not have access to this asset.']);
            }

            return Inertia::render('Assets/Show', [
                'asset' => $assetWithRelations,
                'pageTitle' => 'Asset Details',
                'pageDescription' => 'View detailed information for asset: ' . $assetWithRelations->asset_number
            ]);
        } catch (\Throwable $th) {
            return back()->withErrors(['error' => 'Failed to load asset: ' . $th->getMessage()]);
        }
    }

    /**
     * Show the history of an asset.
     */
    public function showHistory(AssetItem $assetItem)
    {
        try {
            // Get the asset item with its relationships and history
            $assetItemWithHistory = $assetItem->load([
                'assignee',
                'category',
                'type',
                'assetHistory' => function ($query) {
                    $query->orderBy('performed_at', 'desc');
                },
                'asset.region',
                'asset.assetLocation',
                'asset.subLocation',
                'asset.fundSource'
            ]);

            return Inertia::render('Assets/AssetHistory', [
                'assetItem' => $assetItemWithHistory,
                'pageTitle' => 'Asset History',
                'pageDescription' => 'View detailed history for asset item: ' . $assetItem->asset_tag
            ]);
        } catch (\Throwable $th) {
            
            return back()->withErrors(['error' => 'Failed to load asset history: ' . $th->getMessage()]);
        }
    }

    /**
     * Show the history of a specific asset item.
     */
    public function showAssetItemHistory(AssetItem $assetItem)
    {
        try {
            // Get the asset item with its relationships and history
            $assetItemWithHistory = $assetItem->load([
                'assignee',
                'category',
                'type',
                'assetHistory' => function ($query) {
                    $query->orderBy('performed_at', 'desc');
                },
                'asset.region',
                'asset.assetLocation',
                'asset.subLocation',
                'asset.fundSource'
            ]);

            // Get the asset for context
            $asset = $assetItem->asset;

            return Inertia::render('Assets/AssetItemHistory', [
                'asset' => $asset,
                'assetItem' => $assetItemWithHistory,
                'pageTitle' => 'Asset Item History',
                'pageDescription' => 'View detailed history for asset item: ' . $assetItem->asset_tag
            ]);
        } catch (\Throwable $th) {
            return back()->withErrors(['error' => 'Failed to load asset item history: ' . $th->getMessage()]);
        }
    }

    /**
     * Download the asset import template
     */
    public function downloadTemplate()
    {
        try {
            $headers = [
                'Asset Tag',
                'Asset Name',
                'Serial Number',
                'Category',
                'Type',
                'Fund Source',
                'Region',
                'Asset Location',
                'Sub Location',
                'Assignee',
                'Status',
                'Original Value',
                'Acquisition Date'
            ];

            $sampleData = [
                [
                    'ASSET-001',
                    'Laptop Dell XPS 13',
                    'SN123456789',
                    'Electronics',
                    'Laptop',
                    'Government Budget',
                    'Mogadishu',
                    'Main Office',
                    'IT Department',
                    'John Doe',
                    'active',
                    '1200.00',
                    '2024-01-15'
                ],
                [
                    'ASSET-002',
                    'Office Chair',
                    'SN987654321',
                    'Furniture',
                    'Office Chair',
                    'Donor Fund',
                    'Hargeisa',
                    'Branch Office',
                    'Reception',
                    'Jane Smith',
                    'active',
                    '250.00',
                    '2024-01-20'
                ]
            ];

            $filename = 'assets_import_template_' . date('Y-m-d_H-i-s') . '.xlsx';
            
            return Excel::download(new class($headers, $sampleData) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
                private $data;
                private $headers;

                public function __construct($headers, $data)
                {
                    $this->headers = $headers;
                    $this->data = $data;
                }

                public function array(): array
                {
                    return $this->data;
                }

                public function headings(): array
                {
                    return $this->headers;
                }
            }, $filename);

        } catch (\Throwable $th) {
            return back()->withErrors(['error' => 'Failed to download template: ' . $th->getMessage()]);
        }
    }

    /**
     * Import assets from Excel file
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
            ]);

            $file = $request->file('file');
            
            // Import the file with current user ID
            Excel::import(new AssetsImport(auth()->id()), $file);

            return back()->with('success', 'Assets imported successfully!');

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            
            foreach ($failures as $failure) {
                $errors[] = "Row " . ($failure->row() - 1) . ": " . implode(', ', $failure->errors());
            }
            
            return back()->withErrors(['import_errors' => $errors]);
            
        } catch (\Throwable $th) {
            return back()->withErrors(['error' => 'Failed to import assets: ' . $th->getMessage()]);
        }
    }
}
