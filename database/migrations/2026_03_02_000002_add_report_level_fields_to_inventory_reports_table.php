<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add report-level fields to inventory_reports (moved from inventory_report_items).
     */
    public function up(): void
    {
        Schema::table('inventory_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_reports', 'months_of_stock')) {
                $table->string('months_of_stock', 255)->nullable();
            }
            if (!Schema::hasColumn('inventory_reports', 'positive_adjustment')) {
                $table->integer('positive_adjustment')->default(0);
            }
            if (!Schema::hasColumn('inventory_reports', 'negative_adjustment')) {
                $table->integer('negative_adjustment')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_reports', function (Blueprint $table) {
            $table->dropColumn(['months_of_stock', 'positive_adjustment', 'negative_adjustment']);
        });
    }
};
