<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $permissions = [
            // System-Level Permissions (Highest Authority)
            [
                'name' => 'manage-system',
                'display_name' => 'Manage System',
                'description' => 'Full system administration with complete authority over all modules and functions',
                'module' => 'System Administration',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'view-system',
                'display_name' => 'View System',
                'description' => 'View access to all system modules and data without ability to perform actions',
                'module' => 'System Administration',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // User Management Module
            [
                'name' => 'user-view',
                'display_name' => 'View Users',
                'description' => 'Can view user list and details',
                'module' => 'User Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'user-create',
                'display_name' => 'Create Users',
                'description' => 'Can create new users',
                'module' => 'User Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'user-edit',
                'display_name' => 'Edit Users',
                'description' => 'Can edit existing users',
                'module' => 'User Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'user-delete',
                'display_name' => 'Delete Users',
                'description' => 'Can delete users',
                'module' => 'User Management',
                'guard_name' => 'web',
            ],

            // Facility Management Module
            [
                'name' => 'facility-view',
                'display_name' => 'View Facilities',
                'description' => 'Can view facility list and details',
                'module' => 'Facility Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'facility-create',
                'display_name' => 'Create Facilities',
                'description' => 'Can create new facilities',
                'module' => 'Facility Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'facility-edit',
                'display_name' => 'Edit Facilities',
                'description' => 'Can edit existing facilities',
                'module' => 'Facility Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'facility-delete',
                'display_name' => 'Delete Facilities',
                'description' => 'Can delete facilities',
                'module' => 'Facility Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'facility-import',
                'display_name' => 'Import Facilities',
                'description' => 'Can import facilities from Excel',
                'module' => 'Facility Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'facility-manage',
                'display_name' => 'Manage Facilities',
                'description' => 'Can perform all facility management actions (create, edit, delete, import, view)',
                'module' => 'Facility Management',
                'guard_name' => 'web',
            ],

            // Product Management Module
            [
                'name' => 'product-view',
                'display_name' => 'View Products',
                'description' => 'Can view product list and details',
                'module' => 'Product Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'product-create',
                'display_name' => 'Create Products',
                'description' => 'Can create new products',
                'module' => 'Product Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'product-edit',
                'display_name' => 'Edit Products',
                'description' => 'Can edit existing products',
                'module' => 'Product Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'product-delete',
                'display_name' => 'Delete Products',
                'description' => 'Can delete products',
                'module' => 'Product Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'product-import',
                'display_name' => 'Import Products',
                'description' => 'Can import products from Excel',
                'module' => 'Product Management',
                'guard_name' => 'web',
            ],

            // Inventory Management Module
            [
                'name' => 'inventory-view',
                'display_name' => 'View Inventory',
                'description' => 'Can view inventory levels and movements',
                'module' => 'Inventory Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'inventory-adjust',
                'display_name' => 'Adjust Inventory',
                'description' => 'Can adjust inventory quantities',
                'module' => 'Inventory Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'inventory-transfer',
                'display_name' => 'Transfer Inventory',
                'description' => 'Can transfer inventory between locations',
                'module' => 'Inventory Management',
                'guard_name' => 'web',
            ],

            // Warehouse Management Module
            [
                'name' => 'warehouse-view',
                'display_name' => 'View Warehouses',
                'description' => 'Can view warehouse information',
                'module' => 'Warehouse Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'warehouse-manage',
                'display_name' => 'Manage Warehouses',
                'description' => 'Can create, edit, and delete warehouses',
                'module' => 'Warehouse Management',
                'guard_name' => 'web',
            ],

            // Order Management
            [
                'name' => 'order-view',
                'display_name' => 'View Orders',
                'description' => 'Can view order list and details',
                'module' => 'Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'order-create',
                'display_name' => 'Create Orders',
                'description' => 'Can create new orders',
                'module' => 'Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'order-edit',
                'display_name' => 'Edit Orders',
                'description' => 'Can edit existing orders',
                'module' => 'Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'order-delete',
                'display_name' => 'Delete Orders',
                'description' => 'Can delete orders',
                'module' => 'Order Management',
                'guard_name' => 'web',
            ],

            // Transfer Management Module
            [
                'name' => 'transfer-view',
                'display_name' => 'View Transfers',
                'description' => 'Can view transfer list and details',
                'module' => 'Transfer Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'transfer-create',
                'display_name' => 'Create Transfers',
                'description' => 'Can create new transfers',
                'module' => 'Transfer Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'transfer-edit',
                'display_name' => 'Edit Transfers',
                'description' => 'Can edit existing transfers',
                'module' => 'Transfer Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'transfer-delete',
                'display_name' => 'Delete Transfers',
                'description' => 'Can delete transfers',
                'module' => 'Transfer Management',
                'guard_name' => 'web',
            ],

            // Asset Management Module
            [
                'name' => 'asset-view',
                'display_name' => 'View Assets',
                'description' => 'Can view asset list and details',
                'module' => 'Asset Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'asset-create',
                'display_name' => 'Create Assets',
                'description' => 'Can create new assets',
                'module' => 'Asset Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'asset-edit',
                'display_name' => 'Edit Assets',
                'description' => 'Can edit existing assets',
                'module' => 'Asset Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'asset-delete',
                'display_name' => 'Delete Assets',
                'description' => 'Can delete assets',
                'module' => 'Asset Management',
                'guard_name' => 'web',
            ],

            // Liquidate/Disposal Module
            [
                'name' => 'liquidate-view',
                'display_name' => 'View Liquidations',
                'description' => 'Can view liquidation and disposal records',
                'module' => 'Liquidate Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'liquidate-create',
                'display_name' => 'Create Liquidations',
                'description' => 'Can create liquidation and disposal records',
                'module' => 'Liquidate Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'liquidate-edit',
                'display_name' => 'Edit Liquidations',
                'description' => 'Can edit liquidation and disposal records',
                'module' => 'Liquidate Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'liquidate-delete',
                'display_name' => 'Delete Liquidations',
                'description' => 'Can delete liquidation and disposal records',
                'module' => 'Liquidate Management',
                'guard_name' => 'web',
            ],

            // Supply Management Module
            [
                'name' => 'supply-view',
                'display_name' => 'View Supplies',
                'description' => 'Can view supply list and details',
                'module' => 'Supply Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'supply-create',
                'display_name' => 'Create Supplies',
                'description' => 'Can create new supplies',
                'module' => 'Supply Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'supply-edit',
                'display_name' => 'Edit Supplies',
                'description' => 'Can edit existing supplies',
                'module' => 'Supply Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'supply-delete',
                'display_name' => 'Delete Supplies',
                'description' => 'Can delete supplies',
                'module' => 'Supply Management',
                'guard_name' => 'web',
            ],

            // Purchase Order Management Module
            [
                'name' => 'purchase-order-view',
                'display_name' => 'View Purchase Orders',
                'description' => 'Can view purchase order list and details',
                'module' => 'Purchase Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'purchase-order-create',
                'display_name' => 'Create Purchase Orders',
                'description' => 'Can create new purchase orders',
                'module' => 'Purchase Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'purchase-order-edit',
                'display_name' => 'Edit Purchase Orders',
                'description' => 'Can edit existing purchase orders',
                'module' => 'Purchase Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'purchase-order-delete',
                'display_name' => 'Delete Purchase Orders',
                'description' => 'Can delete purchase orders',
                'module' => 'Purchase Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'purchase-order-review',
                'display_name' => 'Review Purchase Orders',
                'description' => 'Can review purchase orders',
                'module' => 'Purchase Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'purchase-order-approve',
                'display_name' => 'Approve Purchase Orders',
                'description' => 'Can approve purchase orders',
                'module' => 'Purchase Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'purchase-order-reject',
                'display_name' => 'Reject Purchase Orders',
                'description' => 'Can reject purchase orders',
                'module' => 'Purchase Order Management',
                'guard_name' => 'web',
            ],

            // Packing List Management Module
            [
                'name' => 'packing-list-view',
                'display_name' => 'View Packing Lists',
                'description' => 'Can view packing list details',
                'module' => 'Packing List Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'packing-list-create',
                'display_name' => 'Create Packing Lists',
                'description' => 'Can create new packing lists',
                'module' => 'Packing List Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'packing-list-edit',
                'display_name' => 'Edit Packing Lists',
                'description' => 'Can edit existing packing lists',
                'module' => 'Packing List Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'packing-list-delete',
                'display_name' => 'Delete Packing Lists',
                'description' => 'Can delete packing lists',
                'module' => 'Packing List Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'packing-list-review',
                'display_name' => 'Review Packing Lists',
                'description' => 'Can review packing lists',
                'module' => 'Packing List Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'packing-list-approve',
                'display_name' => 'Approve Packing Lists',
                'description' => 'Can approve packing lists',
                'module' => 'Packing List Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'packing-list-reject',
                'display_name' => 'Reject Packing Lists',
                'description' => 'Can reject packing lists',
                'module' => 'Packing List Management',
                'guard_name' => 'web',
            ],

            // Reports Module (Update existing)
            [
                'name' => 'reports-view',
                'display_name' => 'View Reports',
                'description' => 'Can view system reports',
                'module' => 'Reports',
                'guard_name' => 'web',
            ],
            [
                'name' => 'reports-export',
                'display_name' => 'Export Reports',
                'description' => 'Can export reports to various formats',
                'module' => 'Reports',
                'guard_name' => 'web',
            ],

            // System Administration Module
            [
                'name' => 'system-settings',
                'display_name' => 'System Settings',
                'description' => 'Can modify system settings',
                'module' => 'System Administration',
                'guard_name' => 'web',
            ],
            [
                'name' => 'permission-manage',
                'display_name' => 'Manage Permissions',
                'description' => 'Can assign and manage user permissions',
                'module' => 'System Administration',
                'guard_name' => 'web',
            ],

            // View-Only Access (Special Permission)
            [
                'name' => 'view-only-access',
                'display_name' => 'View Only Access',
                'description' => 'Can view all modules but cannot perform any actions',
                'module' => 'System Administration',
                'guard_name' => 'web',
            ],

            // Manager System (Full Access)
            [
                'name' => 'manager-system',
                'display_name' => 'System Manager',
                'description' => 'Full system access and management capabilities',
                'module' => 'System Administration',
                'guard_name' => 'web',
            ],

            // Dashboard
            [
                'name' => 'dashboard-view',
                'display_name' => 'View Dashboard',
                'description' => 'Can access and view the dashboard',
                'module' => 'Dashboard',
                'guard_name' => 'web',
            ],

            // Category / Role (used by route middleware)
            [
                'name' => 'category.view',
                'display_name' => 'View Categories',
                'description' => 'Can view categories list and details',
                'module' => 'Category Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'category.create',
                'display_name' => 'Create Categories',
                'description' => 'Can create categories',
                'module' => 'Category Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'category.edit',
                'display_name' => 'Edit Categories',
                'description' => 'Can edit categories',
                'module' => 'Category Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'category.delete',
                'display_name' => 'Delete Categories',
                'description' => 'Can delete categories',
                'module' => 'Category Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'role.view',
                'display_name' => 'View Roles',
                'description' => 'Can view roles and role management screens',
                'module' => 'Role Management',
                'guard_name' => 'web',
            ],

            // Product (frontend uses product_manage)
            [
                'name' => 'product-manage',
                'display_name' => 'Manage Products',
                'description' => 'Can create/edit/delete/import products (module-level)',
                'module' => 'Product Management',
                'guard_name' => 'web',
            ],

            // Inventory (frontend uses inventory_manage)
            [
                'name' => 'inventory-manage',
                'display_name' => 'Manage Inventory',
                'description' => 'Can manage inventory actions (module-level)',
                'module' => 'Inventory Management',
                'guard_name' => 'web',
            ],

            // Orders (workflow permissions used in UI)
            [
                'name' => 'order-manage',
                'display_name' => 'Manage Orders',
                'description' => 'Can manage order workflow actions (module-level)',
                'module' => 'Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'order-review',
                'display_name' => 'Review Orders',
                'description' => 'Can review orders',
                'module' => 'Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'order-approve',
                'display_name' => 'Approve Orders',
                'description' => 'Can approve orders',
                'module' => 'Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'order-reject',
                'display_name' => 'Reject Orders',
                'description' => 'Can reject orders',
                'module' => 'Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'order-processing',
                'display_name' => 'Process Orders',
                'description' => 'Can mark orders as processing / in-progress',
                'module' => 'Order Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'order-dispatch',
                'display_name' => 'Dispatch Orders',
                'description' => 'Can dispatch orders',
                'module' => 'Order Management',
                'guard_name' => 'web',
            ],

            // Transfers (workflow permissions used in UI)
            [
                'name' => 'transfer-manage',
                'display_name' => 'Manage Transfers',
                'description' => 'Can manage transfer workflow actions (module-level)',
                'module' => 'Transfer Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'transfer-review',
                'display_name' => 'Review Transfers',
                'description' => 'Can review transfers',
                'module' => 'Transfer Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'transfer-approve',
                'display_name' => 'Approve Transfers',
                'description' => 'Can approve transfers',
                'module' => 'Transfer Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'transfer-reject',
                'display_name' => 'Reject Transfers',
                'description' => 'Can reject transfers',
                'module' => 'Transfer Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'transfer-processing',
                'display_name' => 'Process Transfers',
                'description' => 'Can mark transfers as processing / in-progress',
                'module' => 'Transfer Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'transfer-dispatch',
                'display_name' => 'Dispatch Transfers',
                'description' => 'Can dispatch transfers',
                'module' => 'Transfer Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'transfer-receive',
                'display_name' => 'Receive Transfers',
                'description' => 'Can receive transfers',
                'module' => 'Transfer Management',
                'guard_name' => 'web',
            ],

            // Wastage (sidebar uses wastage_view)
            [
                'name' => 'wastage-view',
                'display_name' => 'View Wastages',
                'description' => 'Can view wastage / liquidate / disposal records',
                'module' => 'Wastage Management',
                'guard_name' => 'web',
            ],

            // MOH Inventory (permissions used in UI)
            [
                'name' => 'moh-inventory-view',
                'display_name' => 'View MOH Inventory',
                'description' => 'Can view MOH inventory records',
                'module' => 'MOH Inventory',
                'guard_name' => 'web',
            ],
            [
                'name' => 'moh-inventory-create',
                'display_name' => 'Create MOH Inventory',
                'description' => 'Can create/import MOH inventory records',
                'module' => 'MOH Inventory',
                'guard_name' => 'web',
            ],
            [
                'name' => 'moh-inventory-review',
                'display_name' => 'Review MOH Inventory',
                'description' => 'Can review MOH inventory records',
                'module' => 'MOH Inventory',
                'guard_name' => 'web',
            ],
            [
                'name' => 'moh-inventory-approve',
                'display_name' => 'Approve MOH Inventory',
                'description' => 'Can approve MOH inventory records',
                'module' => 'MOH Inventory',
                'guard_name' => 'web',
            ],
            [
                'name' => 'moh-inventory-reject',
                'display_name' => 'Reject MOH Inventory',
                'description' => 'Can reject MOH inventory records',
                'module' => 'MOH Inventory',
                'guard_name' => 'web',
            ],

            // Packing List (UI uses packing_list_update)
            [
                'name' => 'packing-list-update',
                'display_name' => 'Update Packing Lists',
                'description' => 'Can update packing lists after creation',
                'module' => 'Packing List Management',
                'guard_name' => 'web',
            ],

            // Received Backorders (UI uses received_backorder_review/approve)
            [
                'name' => 'received-backorder-view',
                'display_name' => 'View Received Backorders',
                'description' => 'Can view received backorders',
                'module' => 'Supply Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'received-backorder-create',
                'display_name' => 'Create Received Backorders',
                'description' => 'Can create received backorders',
                'module' => 'Supply Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'received-backorder-review',
                'display_name' => 'Review Received Backorders',
                'description' => 'Can review received backorders',
                'module' => 'Supply Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'received-backorder-approve',
                'display_name' => 'Approve Received Backorders',
                'description' => 'Can approve received backorders',
                'module' => 'Supply Management',
                'guard_name' => 'web',
            ],
            [
                'name' => 'received-backorder-reject',
                'display_name' => 'Reject Received Backorders',
                'description' => 'Can reject received backorders',
                'module' => 'Supply Management',
                'guard_name' => 'web',
            ],

            // Reports (some parts of the app use report-view/report-export keys)
            [
                'name' => 'report-view',
                'display_name' => 'View Reports (Legacy)',
                'description' => 'Can view reports (legacy key used in UI/backend)',
                'module' => 'Reports',
                'guard_name' => 'web',
            ],
            [
                'name' => 'report-export',
                'display_name' => 'Export Reports (Legacy)',
                'description' => 'Can export reports (legacy key used in UI/backend)',
                'module' => 'Reports',
                'guard_name' => 'web',
            ],
            [
                'name' => 'report-physical-count-view',
                'display_name' => 'View Physical Count Report',
                'description' => 'Can view physical count reports',
                'module' => 'Reports',
                'guard_name' => 'web',
            ],
            [
                'name' => 'report-physical-count-generate',
                'display_name' => 'Generate Physical Count Report',
                'description' => 'Can generate physical count reports',
                'module' => 'Reports',
                'guard_name' => 'web',
            ],
            [
                'name' => 'report-physical-count-review',
                'display_name' => 'Review Physical Count Report',
                'description' => 'Can review physical count reports',
                'module' => 'Reports',
                'guard_name' => 'web',
            ],
            [
                'name' => 'report-physical-count-approve',
                'display_name' => 'Approve Physical Count Report',
                'description' => 'Can approve physical count reports',
                'module' => 'Reports',
                'guard_name' => 'web',
            ],

            // Settings (view-only seeder expects setting-view)
            [
                'name' => 'setting-view',
                'display_name' => 'View Settings',
                'description' => 'Can view settings screens (read-only)',
                'module' => 'System Administration',
                'guard_name' => 'web',
            ],

            // Suppliers (view-only seeder expects supplier-view)
            [
                'name' => 'supplier-view',
                'display_name' => 'View Suppliers',
                'description' => 'Can view suppliers',
                'module' => 'Supply Management',
                'guard_name' => 'web',
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission['name'],
                'display_name' => $permission['display_name'],
                'description' => $permission['description'],
                'module' => $permission['module'],
                'guard_name' => $permission['guard_name'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command->info('Permissions seeded successfully!');
        $this->command->info('Created ' . count($permissions) . ' permissions across ' . count(array_unique(array_column($permissions, 'module'))) . ' modules.');
    }
}
