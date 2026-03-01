<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Required for Procurement Report: POs Delivery Status (On-time = received on or before expected_date + 2 days, Late = after).
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->date('expected_date')->nullable()->after('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('expected_date');
        });
    }
};
