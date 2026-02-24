<?php

namespace App\Http\Controllers;

use App\Models\Dosage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Http\Resources\DosageResource;
use Inertia\Inertia;
use Throwable;

class DosageController extends Controller
{
    /**
     * Display a listing of the dosages.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the products module.');
        }
        $query = Dosage::query();
        
        // Handle search
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%");;
            });
        }

        $dosages = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
        ->withQueryString();
        $dosages->setPath(url()->current()); // Force Laravel to use full URLs        
        
        
        return Inertia::render('Product/Dosage/Index', [
            'dosages' => DosageResource::collection($dosages),
            'filters' => $request->only('search', 'per_page', 'page'),
        ]);
    }

    /**
     * Show the form for creating a new dosage.
     */
    public function create()
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the products module.');
        }
        return Inertia::render('Product/Dosage/Create');
    }

    /**
     * Show the form for editing a dosage.
     */
    public function edit(Dosage $dosage)
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the products module.');
        }
        return Inertia::render('Product/Dosage/Edit', [
            'dosage' => new DosageResource($dosage)
        ]);
    }

    /**
     * Store a newly created dosage in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            $request->validate([
                'id' => 'nullable',
                'name' => 'required',
            ]);
            
            $dosage = Dosage::updateOrCreate(
                [
                    'id' => $request->id
                ],[
                "name" => $request->name,
            ]);
            
            return response()->json($dosage, 200);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified dosage from storage.
     */
    /**
     * Toggle the active status of a dosage
     */
    public function toggleStatus(Dosage $dosage)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            $dosage->update(['is_active' => !$dosage->is_active]);
            $status = $dosage->is_active ? 'activated' : 'deactivated';
            return response()->json("Dosage {$status} successfully");
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function destroy(Dosage $dosage)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            // Check if the dosage is associated with any products
            if ($dosage->products()->exists()) {
                return response()->json('Cannot delete dosage. It is associated with one or more products.', 500);
            }
            
            $dosage->delete();
            return response()->json('Dosage deleted successfully', 200);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
}
