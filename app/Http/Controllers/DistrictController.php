<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\District;
use App\Http\Resources\DistrictResource;
use Inertia\Inertia;

class DistrictController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('facility-view') && !auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the facilities module.');
        }
        $districts = District::query();

        if($request->filled('search')) {
            $districts->where('district_name', 'LIKE', "%{$request->search}%")
                ->orWhere('region_name', 'LIKE', "%{$request->search}%");
        }
        
        $districts = $districts->paginate(500);

        $regions = District::distinct()->get(['region_name']);
        return Inertia::render('District/Index', [
            'regions' => $regions,
            'districts' => DistrictResource::collection($districts),
            'filters' => $request->only(['search'])
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage facilities.');
        }
        try {
            $validated = $request->validate([
                'id' => 'nullable|exists:districts,id',
                'name' => 'required|string|max:255',
                'region' => 'required|string|max:255',
            ],[
                'name.unique' => $request->name .' already exist'
            ]);
            $district = District::updateOrCreate(['id' => $request->id], $validated);
            return response()->json($district->name, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage facilities.');
        }
        District::destroy($id);
        return response()->json('District deleted successfully', 200);
    }

    public function getDistricts(Request $request)
    {
        if (!auth()->user()->hasPermission('facility-view') && !auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the facilities module.');
        }
        try {
            $query = District::query();
            
            // Only filter by region if it's provided and not null
            if ($request->filled('region') && $request->region !== null) {
                $query->where('region', $request->region);
            }
            
            $districts = $query->pluck('name')->toArray();
            return response()->json($districts, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    // API methods
    public function apiGetDistricts()
    {
        $districts = District::all();
        return response()->json(['districts' => $districts]);
    }

    public function apiGetRegions()
    {
        $regions = District::distinct()->get(['region_name']);
        return response()->json(['regions' => $regions]);
    }
}
