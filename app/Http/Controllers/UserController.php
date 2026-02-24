<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Facility;
use App\Notifications\UserRegistered;
use App\Notifications\PermissionsChanged;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Http\Resources\UserResource;
use App\Models\Permission;
use App\Models\Role;
use App\Events\GlobalPermissionChanged;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();
        
        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        


        // is active
        if ($request->has('status') && $request->status != 'All') {
            $query->where('is_active', $request->status);
        }
        
        // Warehouse filter
        if ($request->filled('warehouse')) {
            $query->whereHas('warehouse', function ($q) use ($request) {
                $q->where('warehouses.name', $request->warehouse);
            });
        }
        
        // Facility filter
        if ($request->filled('facility')) {
            $query->whereHas('facility', function ($q) use ($request) {
                $q->where('facilities.name', $request->facility);
            });
        }

        // Organization filter
        if ($request->filled('organization')) {
            $query->where('organization', $request->organization);
        }

        $query->with(['warehouse', 'facility', 'roles'])->latest();

        
        $users = $query->paginate($request->per_page, ['*'], 'page', $request->page)->withQueryString();

        $users->setPath(url()->current());
        
        logger()->info('Users query result:', [
            'total_users' => $users->total(),
            'current_page_users' => $users->count(),
            'user_organizations' => $users->pluck('organization')->unique()->values()->toArray()
        ]);
        

        
        // Get all warehouses for filtering and selection
        $warehouses = Warehouse::pluck('name')->toArray();

        // Get all facilities for filtering and selection
        $facilities = Facility::pluck('name')->toArray();
        
        $roles = Role::orderBy('name')->get();

        return Inertia::render('User/Index', [
            'users' => UserResource::collection($users),
            'warehouses' => $warehouses,
            'roles' => $roles,
            'filters' => $request->only(['search', 'role', 'status', 'organization', 'warehouse', 'facility', 'per_page']),
            'facilities' => $facilities
        ]);
    }

    /**
     * Store a newly created user or update an existing one.
     */
    public function store(Request $request)
    {
        // Allow creating users even without organization
        // This allows admins to create users and assign organizations
        
        try {
            DB::beginTransaction();
            
            // Normalize potential string values from the UI
            $payload = $request->all();
            // If a warehouse object was sent, extract its id
            if (isset($payload['warehouse']) && is_array($payload['warehouse']) && isset($payload['warehouse']['id'])) {
                $payload['warehouse_id'] = $payload['warehouse']['id'];
            }
            // If a facility object was sent, extract its id
            if (isset($payload['facility']) && is_array($payload['facility']) && isset($payload['facility']['id'])) {
                $payload['facility_id'] = $payload['facility']['id'];
            }
            // Normalize empty strings and string 'null' to null
            $payload['warehouse_id'] = ($payload['warehouse_id'] === '' || $payload['warehouse_id'] === 'null') ? null : $payload['warehouse_id'] ?? null;
            $payload['facility_id'] = ($payload['facility_id'] === '' || $payload['facility_id'] === 'null') ? null : $payload['facility_id'] ?? null;

            // Coerce numeric strings to integers
            if (isset($payload['warehouse_id']) && is_numeric($payload['warehouse_id'])) {
                $payload['warehouse_id'] = (int) $payload['warehouse_id'];
            }
            if (isset($payload['facility_id']) && is_numeric($payload['facility_id'])) {
                $payload['facility_id'] = (int) $payload['facility_id'];
            }

            $request->replace($payload);

            $validationRules = [
                'id' => 'nullable|exists:users,id',
                'name' => 'required|string|max:255',
                'username' => ['required', 'string', 'max:255'],
                'email' => [
                    'required',
                    'email',
                    'unique:users,email,' . $request->id,
                ],
                'organization' => 'required|in:PSI,MoH',
                'warehouse_id' => 'nullable|exists:warehouses,id',
                'password' => $request->id ? 'nullable|string|min:8' : 'required|string|min:8',
                'facility_id' => 'nullable|exists:facilities,id',
                'title' => 'nullable|string|max:255',
                'is_active' => 'nullable|boolean',
            ];

            // Only validate permissions if user doesn't have a facility
            if (!$request->facility_id) {
                $validationRules['permissions'] = 'nullable|array';
                $validationRules['permissions.*'] = 'exists:permissions,id';
            }

            $validationRules['roles'] = 'nullable|array';
            $validationRules['roles.*'] = 'exists:roles,id';

            $request->validate($validationRules);

            $userData = [
                'name' => $request->name,
                'username' => $request->username,
                'title' => $request->filled('title') ? $request->title : null,
                'email' => $request->email,
                'organization' => $request->organization,
                'warehouse_id' => $request->warehouse_id,
                'facility_id' => $request->facility_id,
                'is_active' => $request->has('is_active') ? $request->is_active : true,
            ];

            // Store the original password for email notification
            $originalPassword = null;
            if ($request->filled('password')) {
                $originalPassword = $request->password;
                $userData['password'] = Hash::make($request->password);
            }
            
            // Check if this is a new user or an update
            $isNewUser = !$request->id;
            
            // Create or update the user
            $user = User::updateOrCreate(
                ['id' => $request->id],
                $userData
            );
            
            // Handle roles
            $roleIds = $request->has('roles') && is_array($request->roles) ? $request->roles : [];
            $user->roles()->sync($roleIds);

            // Handle permissions - if user has facility_id, don't assign permissions
            $oldPermissionIds = $request->id ? $user->permissions()->pluck('permissions.id')->toArray() : [];

            if ($request->facility_id) {
                // User has facility, so no permissions should be assigned
                $user->permissions()->sync([]);
                $newPermissionIds = [];
            } else {
                // User has no facility, so assign permissions if provided
                $newPermissionIds = $request->has('permissions') && is_array($request->permissions)
                    ? $request->permissions
                    : [];
                $user->permissions()->sync($newPermissionIds);
            }

            // Notify user by email when permissions were added or removed (updates only)
            if ($request->id) {
                $addedIds = array_values(array_diff($newPermissionIds, $oldPermissionIds));
                $removedIds = array_values(array_diff($oldPermissionIds, $newPermissionIds));
                if (!empty($addedIds) || !empty($removedIds)) {
                    $addedNames = Permission::whereIn('id', $addedIds)->get()->map(fn ($p) => $p->display_name ?: $p->name)->toArray();
                    $removedNames = Permission::whereIn('id', $removedIds)->get()->map(fn ($p) => $p->display_name ?: $p->name)->toArray();
                    $user->notify(new PermissionsChanged($user, $addedNames, $removedNames));
                }
            }
                        
            // Reload the user with relationships for the email
            $user->load(['warehouse', 'facility']);
            
            // Send email notification for new users asynchronously
            if ($isNewUser) {
                // Queue the notification to prevent timeout issues
                $user->notifyNow(new UserRegistered($user, $originalPassword));
            }
            
            DB::commit();
            return response()->json($request->id ? 'User updated successfully' : 'User created successfully', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
        }             
    }



    public function create(Request $request)
    {
        // Allow access to create users even without organization
        // This allows admins to create users and assign organizations
        
        $warehouses = Warehouse::all();
        $permissions = Permission::all();
        $facilities = Facility::all();
        $roles = Role::orderBy('name')->get();
        
        return Inertia::render('User/Create', [
            'warehouses' => $warehouses,
            'permissions' => $permissions,
            'facilities' => $facilities,
            'roles' => $roles,
        ]);
    }
    
    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $request->merge(['id' => $user->id]);
        return $this->store($request);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        // Allow editing users even without organization
        // This allows admins to edit users and assign organizations
        
        // Only restrict cross-organization editing if user has organization
        // if (auth()->check() && auth()->user()->isAdmin()) {
        //     return redirect()->back()->with('error', 'You can only edit users from your organization.');
        // }
        
        $user->load(['permissions', 'roles', 'warehouse', 'facility']);
        $warehouses = Warehouse::all();
        $permissions = Permission::all();
        $facilities = Facility::all();
        $roles = Role::orderBy('name')->get();
        
        return Inertia::render('User/Edit', [
            'user' => $user,
            'warehouses' => $warehouses,
            'permissions' => $permissions,
            'facilities' => $facilities,
            'roles' => $roles,
        ]);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user)
    {
        // Allow deleting users even without organization
        // This allows admins to delete users
        
        // Only restrict cross-organization deletion if user has organization
        if (auth()->check() && auth()->user()->organization && $user->organization !== auth()->user()->organization) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete users from your organization.'
            ], 400);
        }
        
        try {
            // Prevent deleting your own account
            if ($user->id === auth()->id()) {
                $isFromSettings = request()->header('X-From-Settings') || 
                                 (request()->has('_headers') && request()->_headers && isset(request()->_headers['X-From-Settings']));
                
                if ($isFromSettings) {
                    return redirect()->back()->withErrors(['error' => 'You cannot delete your own account.']);
                }
                
                return response()->json('You cannot delete your own account.', 500);
            }
            
            $user->delete();

            $isFromSettings = request()->header('X-From-Settings') || 
                             (request()->has('_headers') && request()->_headers && isset(request()->_headers['X-From-Settings']));
            
            if ($isFromSettings) {
                return redirect()->route('settings.index', ['tab' => 'users'])->with('success', 'User deleted successfully.');
            }

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully.'
            ]);
        } catch (Throwable $e) {
            $isFromSettings = request()->header('X-From-Settings') || 
                             (request()->has('_headers') && request()->_headers && isset(request()->_headers['X-From-Settings']));
            
            if ($isFromSettings) {
                return redirect()->back()->withErrors(['error' => 'An error occurred: ' . $e->getMessage()]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }


    
    /**
     * Toggle a user's active status.
     */
    public function toggleStatus(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'is_active' => 'required|boolean',
        ]);
        
        try {
            $user = User::findOrFail($request->user_id);
            $user->is_active = $request->is_active;
            $user->save();
            
            $statusText = $request->is_active ? 'activated' : 'deactivated';
            
            return response()->json([
                'success' => true,
                'message' => "User {$statusText} successfully",
                'user' => $user
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
    
    /**
     * Toggle status for multiple users.
     */
    public function bulkToggleStatus(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'is_active' => 'required|boolean',
        ]);
        
        try {
            DB::beginTransaction();
            
            $count = User::whereIn('id', $request->user_ids)
                ->update(['is_active' => $request->is_active]);
            
            DB::commit();
            
            $statusText = $request->is_active ? 'activated' : 'deactivated';
            
            return response()->json([
                'success' => true,
                'message' => "{$count} users {$statusText} successfully"
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
