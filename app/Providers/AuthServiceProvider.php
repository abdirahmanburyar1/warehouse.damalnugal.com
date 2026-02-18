<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Permission;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define Gates for all permissions
        $this->definePermissionGates();

        // Define special access gates
        $this->defineSpecialGates();
    }

    /**
     * Define permission-based gates.
     */
    protected function definePermissionGates(): void
    {
        // Get all permissions from database and create gates
        $permissions = Permission::all();

        foreach ($permissions as $permission) {
            Gate::define($permission->name, function (User $user) use ($permission) {
                return $user->hasPermission($permission->name);
            });
        }
    }

    /**
     * Define special access gates for admin and manager roles.
     */
    protected function defineSpecialGates()
    {
        // Admin access - highest level
        Gate::define('admin-access', function ($user) {
            return $user->hasPermissionTo('admin-access') || $user->hasPermissionTo('manage-system');
        });

        // Manager access - high level
        Gate::define('manager-access', function ($user) {
            return $user->hasPermissionTo('manager-access') || $user->hasPermissionTo('manage-system');
        });

        // View-only access - can see but not modify
        Gate::define('view-only', function ($user) {
            return $user->hasPermissionTo('view-only-access') || $user->hasPermissionTo('view-system');
        });

        // System management - full authority
        Gate::define('manage-system', function ($user) {
            return $user->hasPermissionTo('manage-system');
        });

        // System view - view everything but no actions
        Gate::define('view-system', function ($user) {
            return $user->hasPermissionTo('view-system');
        });

        // Module-specific access gates
        Gate::define('user-management', function ($user) {
            return $user->hasPermissionTo('user-view') || $user->hasPermissionTo('manage-system') || $user->hasPermissionTo('view-system');
        });

        Gate::define('facility-management', function ($user) {
            return $user->hasPermissionTo('facility-view') || $user->hasPermissionTo('facility-manage') || $user->hasPermissionTo('manage-system') || $user->hasPermissionTo('view-system');
        });

        Gate::define('facility-manage', function ($user) {
            return $user->hasPermissionTo('facility-manage') || $user->hasPermissionTo('manage-system') || $user->isAdmin;
        });

        Gate::define('product-management', function ($user) {
            return $user->hasPermissionTo('product-view') || $user->hasPermissionTo('manage-system') || $user->hasPermissionTo('view-system');
        });

        Gate::define('inventory-management', function ($user) {
            return $user->hasPermissionTo('inventory-view') || $user->hasPermissionTo('manage-system') || $user->hasPermissionTo('view-system');
        });

        Gate::define('warehouse-management', function ($user) {
            return $user->hasPermissionTo('warehouse-view') || $user->hasPermissionTo('manage-system') || $user->hasPermissionTo('view-system');
        });

        Gate::define('order-management', function ($user) {
            return $user->hasPermissionTo('order-view') || $user->hasPermissionTo('manage-system') || $user->hasPermissionTo('view-system');
        });

        Gate::define('transfer-management', function ($user) {
            return $user->hasPermissionTo('transfer-view') || $user->hasPermissionTo('manage-system') || $user->hasPermissionTo('view-system');
        });

        Gate::define('asset-management', function ($user) {
            return $user->hasPermissionTo('asset-view') || $user->hasPermissionTo('manage-system') || $user->hasPermissionTo('view-system');
        });

        // Asset-specific permission gates
        Gate::define('asset-view', function ($user) {
            return $user->hasPermissionTo('asset-view') || $user->hasPermissionTo('asset-manage') || $user->hasPermissionTo('manage-system') || $user->isAdmin();
        });

        // Gate::define('asset-create', function ($user) {
        //     return $user->hasPermissionTo('asset-create') || $user->hasPermissionTo('asset-manage') || $user->hasPermissionTo('manage-system');
        // });

        Gate::define('asset-edit', function ($user) {
            return $user->hasPermissionTo('asset-edit') || $user->hasPermissionTo('asset-manage') || $user->hasPermissionTo('manage-system') || $user->isAdmin();
        });

        Gate::define('asset-delete', function ($user) {
            return $user->hasPermissionTo('asset-delete') || $user->hasPermissionTo('asset-manage') || $user->hasPermissionTo('manage-system');
        });

        Gate::define('asset-approve', function ($user) {
            return $user->hasPermissionTo('asset-approve') || $user->hasPermissionTo('asset-manage') || $user->hasPermissionTo('manage-system');
        });

        Gate::define('asset-review', function ($user) {
            return $user->hasPermissionTo('asset-review') || $user->hasPermissionTo('asset-manage') || $user->hasPermissionTo('manage-system');
        });

        Gate::define('asset-manage', function ($user) {
            return $user->hasPermissionTo('asset-manage') || $user->hasPermissionTo('manage-system') || $user->isAdmin();
        });

        Gate::define('asset-create', function ($user) {
            return $user->hasPermissionTo('asset-create') || $user->hasPermissionTo('asset-manage') || $user->hasPermissionTo('manage-system') || $user->isAdmin();
        });

        Gate::define('asset-bulk-import', function ($user) {
            return $user->hasPermissionTo('asset-bulk-import') || $user->hasPermissionTo('asset-manage') || $user->hasPermissionTo('manage-system');
        });

        Gate::define('asset-export', function ($user) {
            return $user->hasPermissionTo('asset-export') || $user->hasPermissionTo('asset-manage') || $user->hasPermissionTo('manage-system');
        });

        Gate::define('liquidate-management', function ($user) {
            return $user->hasPermissionTo('liquidate-view') || $user->hasPermissionTo('manage-system') || $user->hasPermissionTo('view-system');
        });

        Gate::define('supply-management', function ($user) {
            return $user->hasPermissionTo('purchase-order-view')
                || $user->hasPermissionTo('packing-list-view')
                || $user->hasPermissionTo('manage-system')
                || $user->hasPermissionTo('view-system');
        });

        Gate::define('report-management', function ($user) {
            return $user->hasPermissionTo('report-view') || $user->hasPermissionTo('manage-system') || $user->hasPermissionTo('view-system');
        });
    }
}
