<?php

namespace App\Traits;

use App\Models\Permission;

trait HasPermissions
{
    /**
     * Check if the user has a specific permission.
     */
    public function hasPermissionTo($permission): bool
    {
        if (is_string($permission)) {
            return $this->hasPermission($permission);
        }

        return false;
    }

    /**
     * Check if the user has any of the given permissions.
     */
    public function hasAnyPermission($permissions): bool
    {
        return $this->hasAnyPermission($permissions);
    }

    /**
     * Check if the user has all of the given permissions.
     */
    public function hasAllPermissions($permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all permissions for the user.
     */
    public function getAllPermissions()
    {
        return $this->permissions;
    }

    /**
     * Get permissions grouped by module.
     */
    public function getPermissionsByModule()
    {
        return $this->permissions->groupBy('module');
    }

    /**
     * Check if user has permission for a specific module.
     */
    public function canAccessModule(string $moduleName): bool
    {
        // System managers can access everything
        if ($this->canManageSystem()) {
            return true;
        }
        
        // View system users can see everything but not perform actions
        if ($this->hasViewSystemAccess()) {
            return true;
        }
        
        // Check specific module permissions
        $modulePermissions = [
            'facilities' => ['facility-view', 'facility-manage'],
            'products' => ['product-view', 'product-manage'],
            'inventory' => ['inventory-view', 'inventory-adjust', 'inventory-transfer', 'inventory-manage'],
            'moh_inventory' => ['moh-inventory-view', 'moh-inventory-create', 'moh-inventory-review', 'moh-inventory-approve', 'moh-inventory-reject'],
            'orders' => ['order-view', 'order-create', 'order-edit', 'order-delete', 'order-manage', 'order-review', 'order-approve', 'order-reject', 'order-processing', 'order-dispatch'],
            'transfers' => ['transfer-view', 'transfer-create', 'transfer-edit', 'transfer-delete', 'transfer-manage', 'transfer-approve'],
            'assets' => ['asset-view', 'asset-create', 'asset-edit', 'asset-delete', 'asset-review', 'asset-approve', 'asset-reject', 'asset-manage', 'asset-bulk-import', 'asset-export'],
            'users' => ['user-view', 'user-create', 'user-edit', 'user-delete'],
            'reports' => ['report-view', 'report-export'],
            'settings' => ['system-settings', 'permission-manage']
        ];
        
        if (isset($modulePermissions[$moduleName])) {
            return $this->hasAnyPermission($modulePermissions[$moduleName]);
        }
        
        return false;
    }

    /**
     * Get user's permission names as an array.
     */
    public function getPermissionNames(): array
    {
        return $this->permissions->pluck('name')->toArray();
    }

    /**
     * Check if user has view-only access.
     */
    public function isViewOnly(): bool
    {
        return $this->hasPermission('view-only-access');
    }

    /**
     * Check if user is a system manager (full authority)
     */
    public function isSystemManager(): bool
    {
        return $this->canManageSystem() || $this->isAdmin();
    }

    /**
     * Check if user can manage the entire system
     */
    public function canManageSystem(): bool
    {
        return $this->hasPermissionTo('manage-system');
    }

    /**
     * Check if user has view-only system access
     */
    public function hasViewSystemAccess(): bool
    {
        return $this->hasPermissionTo('view-system');
    }

    /**
     * Check if user can perform actions (not just view)
     */
    public function canPerformActions(): bool
    {
        return !$this->hasViewSystemAccess() && !$this->isViewOnly();
    }
}
