<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the products module.');
        }
        $query = Category::query();
        
        // Handle search
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }
        
        $categories = $query->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
        ->withQueryString();
    $categories->setPath(url()->current()); // Force Laravel to use full URLs
        
        return Inertia::render('Product/Category/Index', [
            'categories' => CategoryResource::collection($categories),
            'filters' => $request->only(['search', 'per_page', 'page'])
        ]);
    }

    /**
     * Show the form for creating a new category.
     */
    public function create()
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the products module.');
        }
        return Inertia::render('Product/Category/Create');
    }

    /**
     * Show the form for editing a category.
     */
    public function edit(Category $category)
    {
        if (!auth()->user()->hasPermission('product-view') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the products module.');
        }
        return Inertia::render('Product/Category/Edit', [
            'category' => new CategoryResource($category)
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            $request->validate([
                'id' => 'nullable',
                'name' => 'required'
            ]);
            
            $category = Category::updateOrCreate(
                [
                    'id' => $request->id
                ],[
                "name" => $request->name
            ]);
            
            return response()->json($category, 200);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * API endpoint to get categories for AJAX requests
     */
    public function getCategories(Request $request)
    {
        $query = Category::query();
        
        // Handle search
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }
        
        // Handle sorting
        $sortField = $request->input('sort_field', 'id');
        $sortDirection = $request->input('sort_direction', 'desc');
        
        $query->orderBy($sortField, $sortDirection);
        
        // Get paginated results
        $categories = $query->paginate(10)->withQueryString();
        
        return response()->json([
            'success' => true,
            'categoriesPaginated' => CategoryResource::collection($categories),
            'allCategories' => CategoryResource::collection(Category::all())
        ]);
    }

    /**
     * Remove the specified category from storage.
     */
    /**
     * Toggle the active status of a category
     */
    public function toggleStatus(Category $category)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            $category->update(['is_active' => !$category->is_active]);
            $status = $category->is_active ? 'activated' : 'deactivated';
            return response()->json("Category {$status} successfully");
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function destroy(Category $category)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            // Check if category has any products
            if ($category->products()->exists()) {
                return response()->json("Cannot delete category. It is being used by {$category->products()->count()} product(s).", 500);
            }

            $category->delete();
            return response()->json('Category deleted successfully', 200);
        } catch (Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Bulk delete categories
     */
    public function bulkDelete(Request $request)
    {
        if (!auth()->user()->hasPermission('product-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage products.');
        }
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:categories,id'
            ]);

            $categories = Category::whereIn('id', $request->ids)->get();
            
            // Check each category for products
            foreach ($categories as $category) {
                if ($category->products()->exists()) {
                    return response()->json("Cannot delete category '{$category->name}'. It is being used by {$category->products()->count()} product(s).", 500);
                }
            }

            // Delete all categories if none have products
            Category::whereIn('id', $request->ids)->delete();

            return response()->json('Categories deleted successfully', 200);
        } catch (Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
}
