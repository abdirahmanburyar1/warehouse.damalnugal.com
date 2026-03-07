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
        Schema::table('assets', function (Blueprint $table) {
            // Check if district_id doesn't exist before adding it
            if (!Schema::hasColumn('assets', 'district_id')) {
                $table->unsignedBigInteger('district_id')->nullable()->after('region_id');
                $table->foreign('district_id')->references('id')->on('districts');
            }
            
            // Drop columns
            if (Schema::hasColumn('assets', 'asset_location_id')) {
                $table->dropForeign(['asset_location_id']);
                $table->dropColumn('asset_location_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
            $table->dropColumn('district_id');
            
            $table->foreignId('asset_location_id')->nullable()->constrained('asset_locations');
        });
    }
};
