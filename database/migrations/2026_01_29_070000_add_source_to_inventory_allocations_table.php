<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Application creates inventory_allocations with 'source' (e.g. from order update).
     */
    public function up(): void
    {
        Schema::table('inventory_allocations', function (Blueprint $table) {
            $table->string('source')->nullable()->after('allocation_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_allocations', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
