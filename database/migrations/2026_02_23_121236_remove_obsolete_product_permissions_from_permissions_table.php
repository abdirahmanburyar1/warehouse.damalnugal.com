<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove obsolete product permissions (create, edit, delete, import).
     * Only product-view and product-manage are kept.
     */
    public function up(): void
    {
        DB::table('permissions')
            ->whereIn('name', [
                'product-create',
                'product-edit',
                'product-delete',
                'product-import',
            ])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-insert removed permissions so rollback is possible
        $guard = 'web';
        $module = 'Product Management';
        $permissions = [
            ['name' => 'product-create', 'display_name' => 'Create Products', 'description' => 'Can create new products'],
            ['name' => 'product-edit', 'display_name' => 'Edit Products', 'description' => 'Can edit existing products'],
            ['name' => 'product-delete', 'display_name' => 'Delete Products', 'description' => 'Can delete products'],
            ['name' => 'product-import', 'display_name' => 'Import Products', 'description' => 'Can import products from Excel'],
        ];
        $now = now();
        foreach ($permissions as $p) {
            DB::table('permissions')->insert(array_merge($p, [
                'guard_name' => $guard,
                'module' => $module,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }
};
