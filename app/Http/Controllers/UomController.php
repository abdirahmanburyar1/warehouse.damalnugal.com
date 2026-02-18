<?php

namespace App\Http\Controllers;

use App\Models\Uom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Http\Resources\UomResource;
use Inertia\Inertia;
use Throwable;

class UomController extends Controller
{
    /**
     * Display a listing of the UOMs.
     */
    public function index(Request $request)
    {
        $query = Uom::query();
        
        // Handle search
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%");
            });
        }

        $uoms = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
        ->withQueryString();
        $uoms->setPath(url()->current()); // Force Laravel to use full URLs        
        
        return Inertia::render('Product/Uom/Index', [
            'uoms' => UomResource::collection($uoms),
            'filters' => $request->only('search', 'per_page', 'page'),
        ]);
    }

    /**
     * Return UOM names as JSON (for dropdowns / lazy load on focus).
     */
    public function list(Request $request)
    {
        $names = Uom::query()
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->toArray();
        return response()->json($names);
    }

    /**
     * Show the form for creating a new UOM.
     */
    public function create()
    {
        return Inertia::render('Product/Uom/Create');
    }

    /**
     * Show the form for editing a UOM.
     */
    public function edit(Uom $uom)
    {
        return Inertia::render('Product/Uom/Edit', [
            'uom' => new UomResource($uom)
        ]);
    }

    /**
     * Store a newly created UOM in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'id' => 'nullable',
                'name' => 'required|string|max:255|unique:uoms,name,' . ($request->id ?? ''),
            ]);
            
            $uom = Uom::updateOrCreate(
                [
                    'id' => $request->id
                ],[
                "name" => $request->name
            ]);
            
            return response()->json($uom->name, 200);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified UOM in storage.
     */
    public function update(Request $request, Uom $uom)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:uoms,name,' . $uom->id,
            ]);
            
            $uom->update([
                'name' => $request->name,
            ]);
            
            return response()->json($uom, 200);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * Toggle the active status of a UOM
     */
    public function toggleStatus(Uom $uom)
    {
        try {
            $uom->update(['is_active' => !$uom->is_active]);
            $status = $uom->is_active ? 'activated' : 'deactivated';
            return response()->json("UOM {$status} successfully");
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified UOM from storage.
     */
    public function destroy(Uom $uom)
    {
        try {
            $uom->delete();
            return response()->json('UOM deleted successfully', 200);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
}
