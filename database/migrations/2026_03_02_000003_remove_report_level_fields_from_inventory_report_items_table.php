<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove report-level fields from inventory_report_items (moved to inventory_reports).
     */
    public function up(): void
    {
        Schema::table('inventory_report_items', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('inventory_report_items', 'months_of_stock')) {
                $columnsToDrop[] = 'months_of_stock';
            }
            if (Schema::hasColumn('inventory_report_items', 'stockout_days')) {
                $columnsToDrop[] = 'stockout_days';
            }
            if (Schema::hasColumn('inventory_report_items', 'positive_adjustment')) {
                $columnsToDrop[] = 'positive_adjustment';
            }
            if (Schema::hasColumn('inventory_report_items', 'negative_adjustment')) {
                $columnsToDrop[] = 'negative_adjustment';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_report_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_report_items', 'months_of_stock')) {
                $table->string('months_of_stock', 255)->nullable();
            }
            if (!Schema::hasColumn('inventory_report_items', 'stockout_days')) {
                $table->unsignedInteger('stockout_days')->default(0);
            }
            if (!Schema::hasColumn('inventory_report_items', 'positive_adjustment')) {
                $table->integer('positive_adjustment')->default(0);
            }
            if (!Schema::hasColumn('inventory_report_items', 'negative_adjustment')) {
                $table->integer('negative_adjustment')->default(0);
            }
        });
    }
};
