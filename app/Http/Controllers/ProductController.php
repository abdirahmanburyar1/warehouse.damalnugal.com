<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Dosage;
use App\Models\EligibleItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Resources\ProductResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\DosageResource;
use App\Models\FacilityType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use App\Imports\ProductsImport;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to view products.');
        }
        $query = Product::query();
        
        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $eligibleItems = FacilityType::pluck('name')->toArray();

        // EligibleItem
        if($request->filled('eligible')){
            $type = $request->input('eligible');
            if($type == 'All'){
                // Filter products that have eligible items for ALL facility types
                $facilityCount = count($eligibleItems);
                if ($facilityCount > 0) {
                    $query->where(function($q) use ($eligibleItems, $facilityCount) {
                        $q->whereHas('eligible', function ($subQ) use ($eligibleItems) {
                            $subQ->whereIn('facility_type', $eligibleItems);
                        })
                        ->whereRaw('(SELECT COUNT(DISTINCT facility_type) FROM eligible_items WHERE product_id = products.id AND facility_type IN (' . implode(',', array_fill(0, count($eligibleItems), '?')) . ')) = ?', 
                            array_merge($eligibleItems, [$facilityCount]));
                    });
                }
            }else{
                $query->whereHas('eligible', function ($q) use ($type) {
                    $q->where('facility_type', 'like', "%{$type}%");
                });
            }
        }

        // Category filter
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('name', $request->category);
            });
        }

        // status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        // Dosage filter
        if ($request->filled('dosage')) {
            $query->whereHas('dosage', function ($q) use ($request) {
                $q->where('name', $request->dosage);
            });
        }

        $query->with(['category', 'dosage','eligible'])->latest();

        // Sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $query->with('dosage','category');

        $products = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();
        $products->setPath(url()->current()); // Force Laravel to use full URLs


        return Inertia::render('Product/Index', [
            'products' => ProductResource::collection($products),
            'categories' => Category::pluck('name')->toArray(),
            'dosages' => Dosage::pluck('name')->toArray(),
            'eligibleItems' => $eligibleItems,
            'filters' => $request->only(['search', 'category', 'dosage', 'per_page', 'page','eligible', 'status'])
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create()
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to view products.');
        }
        $facilityTypes = FacilityType::where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
        array_unshift($facilityTypes, 'All');

        $supplyClassOptions = static::getSupplyClassOptions();

        return Inertia::render('Product/Create', [
            'categories' => CategoryResource::collection(Category::all()),
            'dosages' => DosageResource::collection(Dosage::all()),
            'facilityTypes' => $facilityTypes,
            'supplyClassOptions' => $supplyClassOptions,
        ]);
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit($id)
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to view products.');
        }
        $product = Product::with(['category', 'dosage', 'eligible'])->findOrFail($id);
        
        // Get facility types from eligible items
        $facilityTypes = $product->eligible->pluck('facility_type')->toArray();
        
        // Add facility_types to the product data
        $productData = $product->toArray();
        $productData['facility_types'] = $facilityTypes;
        $productData['supply_class_values'] = $product->supply_class
            ? array_map('trim', explode(',', $product->supply_class))
            : [];

        $allFacilityTypes = FacilityType::where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
        array_unshift($allFacilityTypes, 'All');
        
        return Inertia::render('Product/Edit', [
            'product' => $productData,
            'categories' => CategoryResource::collection(Category::all()),
            'dosages' => DosageResource::collection(Dosage::all()),
            'facilityTypes' => $allFacilityTypes,
            'supplyClassOptions' => static::getSupplyClassOptions(),
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to create products.');
        }
        try {
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('products', 'name')
                ],
                'category_id' => 'nullable|exists:categories,id',
                'dosage_id' => 'nullable|exists:dosages,id',
                'facility_types' => 'nullable|array',
                'tracert_type' => 'nullable|string',
                'supply_class' => 'nullable',
            ]);

            $supplyClassValue = $request->supply_class;
            if (is_array($supplyClassValue)) {
                $supplyClassValue = implode(',', array_map('trim', array_filter($supplyClassValue)));
            }
            $supplyClassValue = $supplyClassValue ? (string) $supplyClassValue : null;

            DB::beginTransaction();

            $product = Product::create([
                'tracert_type' => $request->tracert_type,
                'name' => $request->name,
                'category_id' => $request->category_id,
                'dosage_id' => $request->dosage_id,
                'supply_class' => $supplyClassValue,
            ]);

            // Assign facility types for new products
            if (!empty($request->facility_types)) {
                $facilityTypes = $request->facility_types;
    
                if (in_array('All', $facilityTypes)) {
                    // Replace "All" with all active facility types from DB
                    $facilityTypes = FacilityType::where('is_active', true)->pluck('name')->toArray();
                }
    
                foreach ($facilityTypes as $type) {
                    EligibleItem::create([
                        'product_id' => $product->id,
                        'facility_type' => $type,
                    ]);
                }
            }
    
            DB::commit();
    
            return response()->json('Product created successfully.', 200);
    
        } catch (Throwable $th) {
            DB::rollBack();
            logger()->error('Product store error', ['error' => $th->getMessage()]);
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to edit products.');
        }
        try {
            $product = Product::with('eligible')->findOrFail($id);

            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('products', 'name')->ignore($id, 'id')
                ],
                'category_id' => 'nullable|exists:categories,id',
                'dosage_id' => 'nullable|exists:dosages,id',
                'tracert_type' => 'nullable|string',
                'facility_types' => 'nullable|array',
                'supply_class' => 'nullable',
            ]);

            $supplyClassValue = $request->supply_class;
            if (is_array($supplyClassValue)) {
                $supplyClassValue = implode(',', array_map('trim', array_filter($supplyClassValue)));
            }
            $supplyClassValue = $supplyClassValue ? (string) $supplyClassValue : null;
    
            DB::beginTransaction();
    
            $product->update([
                'name' => $request->name,
                'category_id' => $request->category_id,
                'dosage_id' => $request->dosage_id,
                'tracert_type' => $request->tracert_type,
                'supply_class' => $supplyClassValue,
            ]);

            // Handle facility types - remove existing and add new ones
            if (isset($request->facility_types)) {
                // Delete all existing eligible items for this product
                $product->eligible()->delete();
                
                // Add new facility types if any are selected
                if (!empty($request->facility_types)) {
                    $facilityTypes = $request->facility_types;
        
                    if (in_array('All', $facilityTypes)) {
                        // Replace "All" with all active facility types from DB
                        $facilityTypes = FacilityType::where('is_active', true)->pluck('name')->toArray();
                    }
        
                    foreach ($facilityTypes as $type) {
                        EligibleItem::create([
                            'product_id' => $product->id,
                            'facility_type' => $type,
                        ]);
                    }
                }
            }
    
            DB::commit();
    
            return response()->json('Product updated successfully.', 200);
    
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $th) {
            DB::rollBack();
            logger()->error('Product update error', ['error' => $th->getMessage()]);
            return response()->json($th->getMessage(), 500);
        }
    }
    

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to delete products.');
        }
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }


    public function importExcel(Request $request)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to import products.');
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
    
            Log::info('Starting product import (synchronous)', [
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'extension' => $extension
            ]);

            $import = new ProductsImport();
            Excel::import($import, $file);

            $results = $import->getResults();

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Import completed: %d product(s) imported, %d skipped.',
                    $results['imported'],
                    $results['skipped']
                ),
                'imported' => $results['imported'],
                'skipped' => $results['skipped'],
                'errors' => $results['errors'],
            ]);
    
        } catch (\Exception $e) {
            Log::error('Product import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sample Excel format and validation rules
     */
    public function getImportFormat()
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to import products.');
        }
        return response()->json([
            'success' => true,
            'data' => [
                'format' => [
                    'headers' => [
                        'item_description' => 'Required - Product name/description (max 255 characters)',
                        'category' => 'Optional - Product category (max 255 characters)',
                        'dosage_form' => 'Optional - Dosage form (max 255 characters)'
                    ],
                    'sample_data' => [
                        [
                            'item_description' => 'Paracetamol 500mg',
                            'category' => 'Pain Relief',
                            'dosage_form' => 'Tablet'
                        ],
                        [
                            'item_description' => 'Amoxicillin 250mg',
                            'category' => 'Antibiotics',
                            'dosage_form' => 'Capsule'
                        ]
                    ],
                    'file_requirements' => [
                        'supported_formats' => ['xlsx', 'xls', 'csv'],
                        'max_file_size' => '50MB',
                        'first_row' => 'Must contain headers',
                        'encoding' => 'UTF-8 recommended'
                    ],
                    'validation_rules' => [
                        'item_description' => 'Required, max 255 characters, must be unique',
                        'category' => 'Optional, max 255 characters, will be created if not exists',
                        'dosage_form' => 'Optional, max 255 characters, will be created if not exists'
                    ]
                ]
            ]
        ]);
    }

    public function toggleStatus(Product $product)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to edit products.');
        }
        try {
            $product->is_active = !$product->is_active;
            $product->save();

            return response()->json($product->is_active ? 'Product activated successfully.' : 'Product deactivated successfully.', 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Get distinct supply class values from products (for dropdown options).
     */
    public static function getSupplyClassOptions(): array
    {
        $values = Product::query()
            ->whereNotNull('supply_class')
            ->where('supply_class', '!=', '')
            ->pluck('supply_class')
            ->flatMap(fn ($v) => array_map('trim', explode(',', $v)))
            ->filter()
            ->unique()
            ->values()
            ->sort()
            ->toArray();

        return array_values($values);
    }
}
