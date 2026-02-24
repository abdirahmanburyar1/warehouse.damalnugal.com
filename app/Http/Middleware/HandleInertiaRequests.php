<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        if ($user && !$user->relationLoaded('roles')) {
            $user->load('roles');
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                // 'permissions' => $user ? ($user->isAdmin() ? \App\Models\Permission::pluck('name') : $user->permissions->pluck('name')) : [],
                'can' => $this->getUserPermissions($request),
                'isAdmin' => $user ? $user->isAdmin() : false,
            ],
            // show warehouse for the current user
            'warehouse' => $user ? $user->warehouse : null,            
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
    
    /**
     * Get the user permissions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function getUserPermissions(Request $request): array
    {
        $user = $request->user();
        
        if (!$user) {
            return [];
        }

        // Admin users get all permissions
        if ($user->isAdmin()) {
            return $this->getAllAvailablePermissions();
        }

        // Ensure permissions are loaded (should already be loaded from share method)
        if (!$user->relationLoaded('permissions')) {
            $user->load('permissions');
        }

        // Get all permissions for the user
        $permissions = $user->permissions->pluck('name');

        // Convert to a flattened can object for easier checking in Vue
        // e.g. 'manage-users' becomes 'manage_users' => true
        $flattenedPermissions = [];
        foreach ($permissions as $permission) {
            // Convert dash notation to underscore for Vue compatibility
            $key = str_replace(['.', '-'], '_', $permission);
            $flattenedPermissions[$key] = true;
        }

        return $flattenedPermissions;
    }

    /**
     * Get all available permissions for admin users.
     *
     * @return array
     */
    protected function getAllAvailablePermissions(): array
    {
        // Cache the permissions to avoid repeated queries
        static $allPermissions = null;
        
        if ($allPermissions === null) {
            $permissions = \App\Models\Permission::pluck('name');
            
            $allPermissions = [];
            foreach ($permissions as $permission) {
                // Convert dash notation to underscore for Vue compatibility
                $key = str_replace(['.', '-'], '_', $permission);
                $allPermissions[$key] = true;
            }
        }

        return $allPermissions;
    }
}
