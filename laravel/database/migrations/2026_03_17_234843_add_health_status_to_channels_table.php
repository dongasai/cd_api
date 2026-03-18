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
        Schema::table('channels', function (Blueprint $table) {
            // 健康状态字段：正常/禁用
            $table->enum('status2', ['normal', 'disabled'])
                ->default('normal')
                ->after('status')
                ->comment('健康状态');

            // 健康状态备注，记录状态变更原因
            $table->text('status2_remark')
                ->nullable()
                ->after('status2')
                ->comment('健康状态备注');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['status2', 'status2_remark']);
        });
    }
};
