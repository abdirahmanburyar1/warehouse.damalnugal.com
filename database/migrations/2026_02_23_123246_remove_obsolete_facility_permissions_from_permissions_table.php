<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove obsolete facility permissions (create, edit, delete, import).
     * Only facility-view and facility-manage are kept.
     */
    public function up(): void
    {
        DB::table('permissions')
            ->whereIn('name', [
                'facility-create',
                'facility-edit',
                'facility-delete',
                'facility-import',
            ])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $guard = 'web';
        $module = 'Facility Management';
        $permissions = [
            ['name' => 'facility-create', 'display_name' => 'Create Facilities', 'description' => 'Can create new facilities'],
            ['name' => 'facility-edit', 'display_name' => 'Edit Facilities', 'description' => 'Can edit existing facilities'],
            ['name' => 'facility-delete', 'display_name' => 'Delete Facilities', 'description' => 'Can delete facilities'],
            ['name' => 'facility-import', 'display_name' => 'Import Facilities', 'description' => 'Can import facilities from Excel'],
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
