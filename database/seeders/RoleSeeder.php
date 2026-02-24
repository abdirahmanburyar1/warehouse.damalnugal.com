<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed default roles (uses existing Spatie roles table).
     */
    public function run(): void
    {
        $defaultRoles = [
            ['name' => 'superadmin', 'guard_name' => 'web'],
            ['name' => 'admin', 'guard_name' => 'web'],
            ['name' => 'warehouse_manager', 'guard_name' => 'web'],
            ['name' => 'facility_user', 'guard_name' => 'web'],
            ['name' => 'viewer', 'guard_name' => 'web'],
        ];

        foreach ($defaultRoles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name'], 'guard_name' => $role['guard_name']],
                $role
            );
        }

        $this->command->info('Default roles seeded.');
    }
}
