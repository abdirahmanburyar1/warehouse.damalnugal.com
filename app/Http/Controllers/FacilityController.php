<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\User;
use App\Models\District;
use App\Models\Region;
use App\Models\FacilityType;
use Illuminate\Http\Request;
use App\Http\Resources\FacilityResource;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use App\Imports\FacilitiesImport;
use Illuminate\Support\Facades\Log;

class FacilityController extends Controller
{
    public function import(Request $request)
    {
        if (!auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage facilities.');
        }

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
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

            Log::info('Starting facilities import (synchronous)', [
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'extension' => $extension
            ]);

            $import = new FacilitiesImport();
            Excel::import($import, $file);

            $results = $import->getResults();

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Import completed: %d facility(ies) imported, %d skipped.',
                    $results['imported'],
                    $results['skipped']
                ),
                'imported' => $results['imported'],
                'skipped' => $results['skipped'],
                'errors' => $results['errors'],
            ]);

        } catch (\Exception $e) {
            Log::error('Facilities import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadTemplate()
    {
        if (!auth()->user()->hasPermission('facility-view') && !auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the facilities module.');
        }
        // Create CSV content with only headers (no sample data)
        $headers = ['facility_name', 'facility type', 'district', 'email', 'phone'];
        
        // Return only headers - frontend will generate XLSX
        $csvContent = implode(',', $headers);
        
        // Return CSV content as plain text for frontend XLSX generation
        return response($csvContent)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="facilities_template_headers.txt"');
    }
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('facility-view') && !auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the facilities module.');
        }

        // Get facility counts before pagination (independent of filters)
        $totalFacilities = Facility::count();
        $activeFacilities = Facility::where('is_active', true)->count();
        $inactiveFacilities = Facility::where('is_active', false)->count();

        // Get paginated facilities with filters
        $facilities = Facility::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search}%");
            })
            ->when($request->filled('district'), function ($query) use ($request) {
                $query->where('district', 'like', "%{$request->district}%");
            })
            ->when($request->filled('facility_type'), function ($query) use ($request) {
                $query->where('facility_type', $request->facility_type);
            })
            ->with(['user','handledby'])
            ->paginate($request->per_page ?? 10, ['*'], 'page', $request->page ?? 1);

        $facilities = $facilities->setPath(url()->current());

        return inertia('Facility/Index', [
            'facilities' => FacilityResource::collection($facilities),
            'facilityCounts' => [
                'total' => $totalFacilities,
                'active' => $activeFacilities,
                'inactive' => $inactiveFacilities,
            ],
            'users' => User::get(),
            'filters' => $request->only('page', 'per_page', 'search', 'district', 'facility_type'),
            'districts' => District::pluck('name')->toArray(),
            'regions' => Region::pluck('name')->toArray(),
            'facilityTypes' => FacilityType::where('is_active', true)->pluck('name')->toArray(),
        ]);
    }

    public function show(Request $request, $id){
        if (!auth()->user()->hasPermission('facility-view') && !auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the facilities module.');
        }

        $facility = Facility::find($id);
        return inertia('Facility/Show', [
            'facility' => $facility,
            'currentTab' => $request->get('tab', 'inventory')
        ]);
    }

    // tabs
    public function inventory(Request $request, $id){
        if (!auth()->user()->hasPermission('facility-view') && !auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the facilities module.');
        }

        $facility = Facility::find($id);
        return inertia('Facility/Inventory', [
            'facility' => $facility,
            'currentTab' => $request->get('tab', 'inventory')
        ]);
    }

    public function dispence(Request $request, $id){
        if (!auth()->user()->hasPermission('facility-view') && !auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the facilities module.');
        }

        $facility = Facility::find($id);
        return inertia('Facility/Dispence', [
            'facility' => $facility,
            'currentTab' => $request->get('tab', 'inventory')
        ]);
    }

    public function stock(Request $request, $id){
        if (!auth()->user()->hasPermission('facility-view') && !auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the facilities module.');
        }

        $facility = Facility::find($id);
        return inertia('Facility/Stock', [
            'facility' => $facility,
            'currentTab' => $request->get('tab', 'inventory')
        ]);
    }

    public function expiry(Request $request, $id){
        if (!auth()->user()->hasPermission('facility-view') && !auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the facilities module.');
        }

        $facility = Facility::find($id);
        return inertia('Facility/Expiry', [
            'facility' => $facility,
            'currentTab' => $request->get('tab', 'inventory')
        ]);
    }
    
    public function create()
    {
        if (!auth()->user()->hasPermission('facility-view') && !auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the facilities module.');
        }

        return inertia('Facility/Create', [
            'users' => User::get(),
            'districts' => District::pluck('name')->toArray(),
            'regions' => Region::pluck('name')->toArray(),
            'facilityTypes' => FacilityType::where('is_active', true)->pluck('name')->toArray(),
        ]);
    }
    
    public function edit(Facility $facility)
    {
        if (!auth()->user()->hasPermission('facility-view') && !auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the facilities module.');
        }

        return inertia('Facility/Edit', [
            'facility' => $facility,
            'users' => User::get(),
            'regions' => Region::pluck('name')->toArray(),
            'facilityTypes' => FacilityType::where('is_active', true)->pluck('name')->toArray(),
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage facilities.');
        }
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:facilities,email,' . $request->id,
                'phone' => 'required|string|max:20',
                'address' => 'required|string|max:255',
                'district' => 'required|string',
                'region' => 'required|string',
                'handled_by' => 'required',
                'facility_type' => 'required|string|max:50',
                'has_cold_storage' => 'boolean',
                'is_active' => 'boolean',
                'user_id' => 'nullable'
            ]);

            $validated['handled_by'] = $request->handled_by['id'];
            $validated['user_id'] = $request->user_id ? $request->user_id['id'] : null;
            Facility::updateOrCreate(['id' => $request->id], $validated);

            return response()->json($request->id ? 'Facility updated successfully.' : 'Facility created successfully.', 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function destroy(Facility $facility)
    {
        if (!auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage facilities.');
        }
        try {
            $facility->delete();    
            return response()->json('Facility deleted successfully.', 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function toggleStatus(Facility $facility)
    {
        if (!auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to manage facilities.');
        }
        try {
            $facility->is_active = !$facility->is_active;
            $facility->save();
            return response()->json('Facility status toggled successfully.', 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function getFacilities(Request $request){
        if (!auth()->user()->hasPermission('facility-view') && !auth()->user()->hasPermission('facility-manage') && !auth()->user()->isAdmin()) {
            abort(403, 'You do not have permission to access the facilities module.');
        }
        try {
            $query = Facility::query();
            
            // Filter by region if provided
            if ($request->filled('region') && $request->region !== null) {
                $query->where('region', $request->region);
            }
            
            // Filter by district if provided (more specific than region)
            if ($request->filled('district') && $request->district !== null) {
                $query->where('district', $request->district);
            }
            
            $facilities = $query->pluck('name')->toArray();
            return response()->json($facilities, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }
}
