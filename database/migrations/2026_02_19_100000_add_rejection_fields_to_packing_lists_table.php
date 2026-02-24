<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds rejection fields for packing list approval workflow (mirroring purchase order).
     */
    public function up(): void
    {
        Schema::table('packing_lists', function (Blueprint $table) {
            if (!Schema::hasColumn('packing_lists', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('packing_lists', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (!Schema::hasColumn('packing_lists', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packing_lists', function (Blueprint $table) {
            if (Schema::hasColumn('packing_lists', 'rejected_by')) {
                $table->dropForeign(['rejected_by']);
            }
            if (Schema::hasColumn('packing_lists', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
            if (Schema::hasColumn('packing_lists', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });
    }
};
