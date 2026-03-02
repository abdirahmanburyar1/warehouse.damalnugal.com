<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add stockout_days to inventory_report_items (calculated per product/warehouse:
     * days between last issue date and next receive date in the report month).
     */
    public function up(): void
    {
        Schema::table('inventory_report_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_report_items', 'stockout_days')) {
                $table->unsignedInteger('stockout_days')->default(0)->after('average_monthly_consumption');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_report_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_report_items', 'stockout_days')) {
                $table->dropColumn('stockout_days');
            }
        });
    }
};
