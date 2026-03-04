<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds indexes to orders and transfers tables for improved query performance.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');
            $table->index('order_date');
            $table->index('deleted_at');
            $table->index('created_at');
            $table->index(['facility_id', 'status', 'order_date']);
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->index('status');
            $table->index('transfer_date');
            $table->index('deleted_at');
            $table->index('created_at');
            $table->index(['from_warehouse_id', 'status', 'transfer_date']);
            $table->index(['to_warehouse_id', 'status', 'transfer_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['order_date']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['facility_id', 'status', 'order_date']);
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['transfer_date']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['from_warehouse_id', 'status', 'transfer_date']);
            $table->dropIndex(['to_warehouse_id', 'status', 'transfer_date']);
        });
    }
};
