<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('e.g. expiry_items');
            $table->boolean('enabled')->default(false);
            $table->json('config')->nullable()->comment('e.g. {"role_ids": [1,2], "days_before_expiry": 90}');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_notification_settings');
    }
};
