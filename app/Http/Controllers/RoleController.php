<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RoleController extends Controller
{
    /**
     * List roles and show create form (settings).
     */
    public function index()
    {
        $roles = Role::orderBy('name')->get();

        return Inertia::render('Settings/Role/Index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->where('guard_name', 'web'),
            ],
            'guard_name' => 'nullable|string|in:web',
        ]);

        $guardName = $validated['guard_name'] ?? 'web';
        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => $guardName,
        ]);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role,
        ], 201);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role)
    {
        try {
            $role->delete();
            return response()->json(['message' => 'Role deleted successfully'], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
