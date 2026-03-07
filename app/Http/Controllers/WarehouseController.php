<?php

namespace App\Http\Controllers;

use App\Http\Resources\WarehouseResource;
use App\Models\Category;
use App\Models\Warehouse;
use App\Models\Region;
use Illuminate\Http\Request;
use App\Models\District;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Throwable;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $warehouses = Warehouse::query();

        if($request->filled('search')){
            $warehouses->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('address', 'like', '%' . $request->search . '%');
        }

        if($request->filled('status')){
            $warehouses->where('status', $request->status);
        }

        // region
        if($request->filled('region')){
            $warehouses->where('region', $request->region);
        }
        // district
        if($request->filled('district')){
            $warehouses->where('district', $request->district);
        }

        $warehouses->latest();

        $warehouses = $warehouses->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $warehouses->setPath(url()->current());

        return Inertia::render('Warehouse/Index', [
            'warehouses' => WarehouseResource::collection($warehouses),
            'filters' => $request->only('search','per_page','page','status'),
            'regions' => Region::pluck('name')->toArray(),
            'districts' => District::pluck('name')->toArray(),
        ]);
    }

    public function store(Request $request)
    {
        try {            
        $request->validate([
            'id' => 'nullable|exists:warehouses,id',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'manager_name' => 'nullable|string|max:255',
            'manager_email' => 'nullable|email|max:255',
            'manager_phone' => 'nullable|string|max:50',
            'status' => 'string|max:50',
        ]);
        dd($request->all());

            $warehouse = Warehouse::updateOrCreate(
                ['id' => $request->id],
                [
                    'name' => $request->name,
                    'address' => $request->address,
                    'region' => $request->region,
                    'district' => $request->district,
                    'manager_name' => $request->manager_name,
                    'manager_phone' => $request->manager_phone,
                    'manager_email' => $request->manager_email,
                    'status' => $request->status ?? 'active',
                ]
            );

            $message = $request->id ? 'Warehouse updated successfully' : 'Warehouse created successfully';
            return response()->json($message, 200);
        } catch (Throwable $e) {
            return response()->json( $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified warehouse.
     */
    public function destroy(Warehouse $warehouse)
    {
        try {
            $warehouse->delete();
            return response()->json('Warehouse deleted successfully', 200);
        } catch (Throwable $e) {
            return response()->json('Error deleting warehouse: ' . $e->getMessage(), 500);
        }
    }

    
    /**
     * Show the form for editing the specified warehouse.
     */
    public function edit($id)
    {
        // Make sure the warehouse exists
        $warehouse = Warehouse::find($id);
        if (!$warehouse) {
            return redirect()->route('inventories.warehouses.index')->with('error', 'Warehouse not found');
        }
        
        return Inertia::render('Warehouse/Edit', [
            'warehouse' => $warehouse,
            'regions' => Region::pluck('name')->toArray()
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Warehouse/Create', [
            'regions' => Region::pluck('name')->toArray()
        ]);
    }
    
    /**
     * Get all warehouses for API use (dropdowns, etc.)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWarehouses()
    {
        try {
            $warehouses = Warehouse::where('status', 'active')
                ->where('id', auth()->user()->warehouse_id)
                ->orderBy('name')
                ->get()
                ->map(function($warehouse) {
                    return [
                        'id' => $warehouse->id,
                        'name' => $warehouse->name,
                        'code' => $warehouse->code
                    ];
                });
                
            return response()->json($warehouses);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Failed to fetch warehouses'], 500);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $warehouse = Warehouse::findOrFail($id);
            $warehouse->status = $warehouse->status == 'active' ? 'inactive' : 'active';
            $warehouse->save();
            return response()->json('Warehouse status toggled successfully', 200);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getWarehousesPluck(Request $request){
        try {
            $query = Warehouse::query();
            
            // Filter by region if provided
            if ($request->filled('region') && $request->region !== null) {
                $query->where('region', $request->region);
            }
            
            // Filter by district if provided (more specific than region)
            if ($request->filled('district') && $request->district !== null) {
                $query->where('district', $request->district);
            }
            
            $warehouses = $query->pluck('name')->toArray();
            return response()->json($warehouses, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

}
