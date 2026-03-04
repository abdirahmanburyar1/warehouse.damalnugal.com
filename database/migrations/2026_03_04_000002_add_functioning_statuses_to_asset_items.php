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
