<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_reports', 'stockout_days')) {
                $table->unsignedInteger('stockout_days')->default(0)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_reports', function (Blueprint $table) {
            $table->dropColumn('stockout_days');
        });
    }
};
