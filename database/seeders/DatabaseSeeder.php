<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,    // Create all permissions first
            RoleSeeder::class,           // Create default roles (uses roles table)
            UserSeeder::class,          // Create Super Admin user with all permissions
            ViewOnlyUserSeeder::class,  // Create View-Only user with restricted permissions
            SomaliRegionsDistrictsSeeder::class, // Create Somali regions and districts
            SystemLevelUsersSeeder::class, // Add this line
        ]);
    }
}
