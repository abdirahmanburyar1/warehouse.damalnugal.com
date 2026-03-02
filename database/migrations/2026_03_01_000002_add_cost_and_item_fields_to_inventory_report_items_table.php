<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_report_items', function (Blueprint $table) {
            $table->string('uom')->nullable()->after('warehouse_id');
            $table->string('batch_number')->nullable()->after('uom');
            $table->date('expiry_date')->nullable()->after('batch_number');
            $table->decimal('unit_cost', 12, 2)->default(0)->after('quantity_in_pipeline');
            $table->decimal('total_cost', 12, 2)->default(0)->after('unit_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_report_items', function (Blueprint $table) {
            $table->dropColumn(['uom', 'batch_number', 'expiry_date', 'unit_cost', 'total_cost']);
        });
    }
};
