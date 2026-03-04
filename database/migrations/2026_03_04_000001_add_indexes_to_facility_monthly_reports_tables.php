<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds indexes to facility_monthly_reports and facility_monthly_report_items
     * for efficient lookups (1000+ facilities × 500+ items each).
     */
    public function up(): void
    {
        if (! Schema::hasTable('facility_monthly_reports')) {
            return;
        }

        $reportIndexes = collect(DB::select('SHOW INDEX FROM facility_monthly_reports'))
            ->pluck('Key_name')->unique()->values();

        Schema::table('facility_monthly_reports', function (Blueprint $table) use ($reportIndexes) {
            if (! $reportIndexes->contains('fmr_facility_period')) {
                $table->index(['facility_id', 'report_period'], 'fmr_facility_period');
            }
            if (! $reportIndexes->contains('fmr_facility_id')) {
                $table->index(['facility_id'], 'fmr_facility_id');
            }
            if (! $reportIndexes->contains('fmr_report_period')) {
                $table->index(['report_period'], 'fmr_report_period');
            }
            if (! $reportIndexes->contains('fmr_status')) {
                $table->index(['status'], 'fmr_status');
            }
        });

        if (! Schema::hasTable('facility_monthly_report_items')) {
            return;
        }

        $itemIndexes = collect(DB::select('SHOW INDEX FROM facility_monthly_report_items'))
            ->pluck('Key_name')->unique()->values();

        Schema::table('facility_monthly_report_items', function (Blueprint $table) use ($itemIndexes) {
            if (! $itemIndexes->contains('fmri_parent_id')) {
                $table->index('parent_id', 'fmri_parent_id');
            }
            if (! $itemIndexes->contains('fmri_product_id')) {
                $table->index('product_id', 'fmri_product_id');
            }
            if (! $itemIndexes->contains('fmri_parent_product')) {
                $table->index(['parent_id', 'product_id'], 'fmri_parent_product');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('facility_monthly_reports')) {
            Schema::table('facility_monthly_reports', function (Blueprint $table) {
                $table->dropIndex('fmr_facility_period');
                $table->dropIndex('fmr_facility_id');
                $table->dropIndex('fmr_report_period');
                $table->dropIndex('fmr_status');
            });
        }

        if (Schema::hasTable('facility_monthly_report_items')) {
            Schema::table('facility_monthly_report_items', function (Blueprint $table) {
                $table->dropIndex('fmri_parent_id');
                $table->dropIndex('fmri_product_id');
                $table->dropIndex('fmri_parent_product');
            });
        }
    }
};
