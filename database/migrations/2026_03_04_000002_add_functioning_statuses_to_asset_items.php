<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds 'functioning' and 'not_functioning' to asset_items status enum.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE asset_items MODIFY COLUMN status ENUM(
            'pending_approval',
            'in_use',
            'maintenance',
            'retired',
            'disposed',
            'Good',
            'Non-functional',
            'functioning',
            'not_functioning'
        ) DEFAULT 'pending_approval'");

        // Migrate existing active/inactive to functioning/not_functioning
        try {
            DB::table('asset_items')->where('status', 'active')->update(['status' => 'functioning']);
            DB::table('asset_items')->where('status', 'inactive')->update(['status' => 'not_functioning']);
        } catch (\Throwable $e) {
            // Ignore if active/inactive not in enum
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE asset_items MODIFY COLUMN status ENUM(
            'pending_approval',
            'in_use',
            'maintenance',
            'retired',
            'disposed',
            'Good',
            'Non-functional'
        ) DEFAULT 'pending_approval'");
    }
};
