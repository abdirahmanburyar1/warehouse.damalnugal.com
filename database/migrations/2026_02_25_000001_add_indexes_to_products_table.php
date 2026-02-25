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
        Schema::table('products', function (Blueprint $table) {
            $table->index('name');
            $table->index('is_active');
            $table->index('created_at');
            $table->index('supply_class');
            // Composite index for common list filter: active products ordered by name
            $table->index(['is_active', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_name_index');
            $table->dropIndex('products_is_active_index');
            $table->dropIndex('products_created_at_index');
            $table->dropIndex('products_supply_class_index');
            $table->dropIndex('products_is_active_name_index');
        });
    }
};
