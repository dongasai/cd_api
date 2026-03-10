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
        // 给 request_logs 表添加字段
        Schema::table('request_logs', function (Blueprint $table) {
            // 运行唯一标识，用于追踪整个请求生命周期
            $table->string('run_unid', 50)->nullable()->after('request_id')->comment('运行唯一标识');
            // 发往渠道的请求参数
            $table->longText('to_request_body')->nullable()->after('body_text')->comment('发往渠道的请求参数');
            // 上游渠道信息
            $table->unsignedBigInteger('channel_id')->nullable()->after('run_unid')->comment('渠道 ID');
            $table->string('channel_name', 100)->nullable()->after('channel_id')->comment('渠道名称');
            $table->string('upstream_model', 100)->nullable()->after('model')->comment('上游实际使用的模型');
        });

        // 给 audit_logs 表添加字段
        Schema::table('audit_logs', function (Blueprint $table) {
            // 运行唯一标识，用于追踪整个请求生命周期
            $table->string('run_unid', 50)->nullable()->after('request_id')->comment('运行唯一标识');
        });

        // 添加索引
        Schema::table('request_logs', function (Blueprint $table) {
            $table->index('run_unid', 'request_logs_run_unid_index');
            $table->index('channel_id', 'request_logs_channel_id_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('run_unid', 'audit_logs_run_unid_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_logs', function (Blueprint $table) {
            $table->dropIndex('request_logs_run_unid_index');
            $table->dropIndex('request_logs_channel_id_index');
            $table->dropColumn(['run_unid', 'to_request_body', 'channel_id', 'channel_name', 'upstream_model']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_run_unid_index');
            $table->dropColumn('run_unid');
        });
    }
};
