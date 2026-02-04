<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class BootstrapSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Ensure the superadmin user exists (and is active)
        $user = User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'email' => 'abdirahman.buryar@gmail.com',
                'password' => Hash::make('password'),
                'title' => 'Super Administrator',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // 2) Assign ALL permissions to the user via the app's pivot table
        $permissions = Permission::all();
        if (Schema::hasTable('permission_user')) {
            $user->permissions()->sync($permissions->pluck('id'));
        }

        // 3) Create/ensure a "superadmin" role exists (Spatie tables already exist in DB)
        $roleId = null;
        if (Schema::hasTable('roles')) {
            $roleId = DB::table('roles')->where('name', 'superadmin')->where('guard_name', 'web')->value('id');
            if (!$roleId) {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => 'superadmin',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 4) Assign ALL permissions to the role (role_has_permissions)
        if ($roleId && Schema::hasTable('role_has_permissions')) {
            // Clear existing mappings for this role, then re-insert
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            $rows = $permissions->map(fn ($p) => [
                'permission_id' => $p->id,
                'role_id' => $roleId,
            ])->all();
            if (!empty($rows)) {
                DB::table('role_has_permissions')->insert($rows);
            }
        }

        // 5) Assign the role to the user (model_has_roles)
        if ($roleId && Schema::hasTable('model_has_roles')) {
            // This table uses Spatie's default: role_id, model_type, model_id
            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $roleId,
                    'model_type' => User::class,
                    'model_id' => $user->id,
                ],
                []
            );
        }

        $this->command->info('Bootstrap superadmin completed.');
        $this->command->info('Username: superadmin');
        $this->command->info('Email: abdirahman.buryar@gmail.com');
        $this->command->info('Password: password');
        $this->command->info('Permissions assigned: ' . $permissions->count());
    }
}

