<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为 channels 表添加 rpm_limit 字段
 *
 * 用于渠道整体级别的 RPM 限流控制
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            // 渠道整体 RPM 限制
            $table->integer('rpm_limit')->nullable()->default(null)
                ->comment('渠道整体 RPM（每分钟请求）限制，null 表示使用系统默认值');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('rpm_limit');
        });
    }
};
