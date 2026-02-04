<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Back orders from packing lists (source_type = 'packing_list') are not linked
     * to an order or transfer, so order_id and transfer_id must be nullable.
     */
    public function up(): void
    {
        Schema::table('back_orders', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['transfer_id']);
        });

        DB::statement('ALTER TABLE back_orders MODIFY order_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE back_orders MODIFY transfer_id BIGINT UNSIGNED NULL');

        Schema::table('back_orders', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('transfer_id')->references('id')->on('transfers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('back_orders', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['transfer_id']);
        });

        DB::statement('ALTER TABLE back_orders MODIFY order_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE back_orders MODIFY transfer_id BIGINT UNSIGNED NOT NULL');

        Schema::table('back_orders', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('transfer_id')->references('id')->on('transfers')->onDelete('cascade');
        });
    }
};
