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
        Schema::table('audit_logs', function (Blueprint $table) {
            // 新增 apply_data 字段，用于记录模型流转过程数据
            $table->json('apply_data')
                ->nullable()
                ->after('metadata')
                ->comment('应用数据：参与匹配模型、渠道请求模型等');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // 删除 apply_data 字段
            $table->dropColumn('apply_data');
        });
    }
};
