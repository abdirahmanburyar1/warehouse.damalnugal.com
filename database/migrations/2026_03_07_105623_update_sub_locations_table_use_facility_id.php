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
        Schema::table('sub_locations', function (Blueprint $table) {
            // Explicitly drop foreign key before dropping the column
            $foreignKeys = Schema::getForeignKeys('sub_locations');
            foreach ($foreignKeys as $key) {
                if (in_array('asset_location_id', $key['columns'])) {
                    $table->dropForeign($key['name']);
                    break;
                }
            }

            if (Schema::hasColumn('sub_locations', 'asset_location_id')) {
                $table->dropColumn('asset_location_id');
            }
            
            if (!Schema::hasColumn('sub_locations', 'facility_id')) {
                $table->unsignedBigInteger('facility_id')->nullable()->after('name');
                $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_locations', function (Blueprint $table) {
            $table->dropForeign(['facility_id']);
            $table->dropColumn('facility_id');
            $table->foreignId('asset_location_id')->constrained('asset_locations');
        });
    }
};
