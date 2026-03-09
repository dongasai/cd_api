<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropIndex(['status', 'health_status']);
            $table->dropColumn('health_status');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->enum('health_status', ['healthy', 'unhealthy', 'unknown'])->default('unknown')->comment('健康状态');
            $table->index(['status', 'health_status']);
        });
    }
};
