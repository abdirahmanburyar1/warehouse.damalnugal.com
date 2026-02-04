<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issued_quantities', function (Blueprint $table) {
            if (!Schema::hasColumn('issued_quantities', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->index()->after('id');
            }

            if (!Schema::hasColumn('issued_quantities', 'transfer_id')) {
                $table->unsignedBigInteger('transfer_id')->nullable()->index()->after('order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('issued_quantities', function (Blueprint $table) {
            if (Schema::hasColumn('issued_quantities', 'transfer_id')) {
                $table->dropColumn('transfer_id');
            }
            if (Schema::hasColumn('issued_quantities', 'order_id')) {
                $table->dropColumn('order_id');
            }
        });
    }
};

