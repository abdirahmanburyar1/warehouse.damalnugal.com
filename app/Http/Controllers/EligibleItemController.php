<?php

namespace App\Http\Controllers;

use App\Http\Resources\EligibleItemResource;
use App\Models\EligibleItem;
use App\Models\Product;
use App\Models\Facility;
use App\Models\FacilityType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Jobs\ImportEligibleItemsJob;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessEligibleItemImport;
use App\Imports\EligibleItemImport;
use App\Http\Resources\FacilityTypeResource;

class EligibleItemController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the products module.');
        }
        $query = FacilityType::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('facility_type')) {
            $type = $request->input('facility_type');
            if($type == 'All'){
                // Get all active facility types
                $facilityTypes = FacilityType::where('is_active', true)->pluck('name')->toArray();
                $query->whereIn('name', $facilityTypes);
            }else{
                $query->where('name', 'like', "%{$type}%");
            }
        }
        
        $query = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
        ->withQueryString();
        $query->setPath(url()->current()); // Force Laravel to use full URLs

        // Get facility types for filter dropdown
        $facilityTypes = FacilityType::where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        return Inertia::render('Product/Eligible/Index', [
            'eligibleItems' => FacilityTypeResource::collection($query),
            'filters' => $request->only(['search', 'per_page','facility_type']),
            'facilityTypes' => $facilityTypes,
        ]);
    }

    public function create()
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the products module.');
        }
        // Get facility types for dropdown
        $facilityTypes = FacilityType::where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        return Inertia::render('Product/Eligible/Create', [
            'products' => Product::select('id', 'name')->orderBy('name')->get(),
            'facilityTypes' => $facilityTypes,
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            $request->validate([
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|exists:products,id',
                'facility_types' => 'required|array|min:1',
            ]);
    
            // Get all active facility types from database
            $allTypes = FacilityType::where('is_active', true)->pluck('name')->toArray();
    
            foreach ($request->products as $product) {
                $productId = $product['product_id'];
    
                if (in_array('All', $request->facility_types)) {
                    // Check which types already exist
                    $existingTypes = EligibleItem::where('product_id', $productId)
                        ->pluck('facility_type')
                        ->toArray();
    
                    // Get types that are missing
                    $missingTypes = array_diff($allTypes, $existingTypes);
    
                    foreach ($missingTypes as $type) {
                        EligibleItem::firstOrCreate([
                            'product_id' => $productId,
                            'facility_type' => $type,
                        ]);
                    }
                } else {
                    foreach ($request->facility_types as $type) {
                        EligibleItem::firstOrCreate([
                            'product_id' => $productId,
                            'facility_type' => $type,
                        ]);
                    }
                }
            }
    
            return response()->json("Created eligible items successfully", 200);
        } catch (\Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
    

    public function update(Request $request)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            $request->validate([
                'id' => 'required|exists:eligible_items,id',
                'product_id' => 'required|exists:products,id',
                'facility_type' => 'required'
            ]);

            $eligibleItem = EligibleItem::updateOrCreate([
                'id' => $request->id
            ],[
                'product_id' => $request->product_id,
                'facility_type' => $request->facility_type,
            ]);
           
            return response()->json("Successfully updated eligible item", 200);

        } catch (\Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function edit($eligibleItem)
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the products module.');
        }
        $eligible = EligibleItem::with('product')->find($eligibleItem);
        
        // Get facility types for dropdown
        $facilityTypes = FacilityType::where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
            
        return inertia('Product/Eligible/Edit', [
            'eligible' => $eligible,
            'products' => Product::select('id', 'name')->orderBy('name')->get(),
            'facilityTypes' => $facilityTypes,
        ]);
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            $eligible = EligibleItem::find($id);
            if (!$eligible) {
                return response()->json('Eligible item not found', 500);
            }
            $eligible->delete();

            return response()->json('Eligible item deleted successfully.', 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
    
    /**
     * Import eligible items from Excel file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        $tempFile = null;
        try {
            if (!$request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file was uploaded'
                ], 422);
            }

            $file = $request->file('file');
            $tempFile = $file->getPathname(); // Get temporary file path
            
            // Validate file
            if (!$file->isValid() || !in_array($file->getClientOriginalExtension(), ['xlsx', 'xls', 'csv'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file type. Please upload an Excel file (.xlsx, .xls) or CSV file'
                ], 422);
            }

            // Generate a unique import ID
            $importId = uniqid('import_');
            
            // Store file in public/excel-imports directory
            $fileName = $importId . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('excel-imports', $fileName, 'public');
            $fullPath = storage_path('app/public/' . $filePath);

            // Import the file directly
            $import = new EligibleItemImport($importId);
            Excel::import($import, $fullPath);

            // Clean up the temporary file
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully. Import is being processed in the background.',
                'import_id' => $importId
            ]);

        } catch (\Exception $e) {
            // Clean up temporary file if it exists
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }

            logger()->error('Import error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
