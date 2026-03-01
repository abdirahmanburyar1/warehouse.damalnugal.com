<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds indexes to assets and asset_items for large datasets (50k+ rows).
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->index('deleted_at');
            $table->index('organization');
            $table->index('acquisition_date');
            $table->index(['facility_id', 'deleted_at']);
            $table->index(['region_id', 'deleted_at']);
            $table->index(['asset_location_id', 'deleted_at']);
        });

        Schema::table('asset_items', function (Blueprint $table) {
            $table->index('deleted_at');
            $table->index('status');
            $table->index('created_at');
            $table->index(['asset_id', 'deleted_at']);
            $table->index(['asset_category_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['organization']);
            $table->dropIndex(['acquisition_date']);
            $table->dropIndex(['facility_id', 'deleted_at']);
            $table->dropIndex(['region_id', 'deleted_at']);
            $table->dropIndex(['asset_location_id', 'deleted_at']);
        });

        Schema::table('asset_items', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['asset_id', 'deleted_at']);
            $table->dropIndex(['asset_category_id', 'deleted_at']);
        });
    }
};
