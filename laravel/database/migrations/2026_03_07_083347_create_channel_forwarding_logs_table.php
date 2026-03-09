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
        Schema::create('channel_forwarding_logs', function (Blueprint $table) {
            $table->id();

            // 关联信息
            $table->unsignedBigInteger('audit_log_id')->comment('关联审计日志 ID');
            $table->string('request_id', 50)->comment('请求唯一标识');

            // 转发链路
            $table->unsignedTinyInteger('hop_index')->default(0)->comment('转发跳数 (0=首次, 1=第一次重试)');
            $table->unsignedBigInteger('channel_id')->comment('渠道 ID');
            $table->string('channel_name', 100)->nullable()->comment('渠道名称 (冗余)');
            $table->string('group_name', 50)->nullable()->comment('分组名称');

            // 上游请求
            $table->string('upstream_url', 500)->nullable()->comment('上游请求 URL');
            $table->string('upstream_method', 10)->nullable()->comment('上游请求方法');
            $table->json('upstream_headers')->nullable()->comment('上游请求头');
            $table->text('upstream_body_snippet')->nullable()->comment('上游请求体摘要');

            // 上游响应
            $table->unsignedSmallInteger('upstream_status')->nullable()->comment('上游响应状态码');
            $table->json('upstream_headers_response')->nullable()->comment('上游响应头');
            $table->text('upstream_body_snippet_response')->nullable()->comment('上游响应体摘要');
            $table->unsignedInteger('upstream_latency_ms')->default(0)->comment('上游响应延迟');

            // 转发结果
            $table->boolean('is_success')->default(false)->comment('是否成功');
            $table->boolean('is_final')->default(false)->comment('是否最终响应');
            $table->string('skip_reason', 100)->nullable()->comment('跳过原因');
            $table->string('fallback_reason', 100)->nullable()->comment('降级原因');

            // 错误信息
            $table->string('error_type', 100)->nullable()->comment('错误类型');
            $table->text('error_message')->nullable()->comment('错误消息');

            // 时间记录
            $table->timestamp('started_at', 3)->nullable()->comment('转发开始时间');
            $table->timestamp('ended_at', 3)->nullable()->comment('转发结束时间');
            $table->timestamps();

            // 索引
            $table->index('audit_log_id');
            $table->index('request_id');
            $table->index(['channel_id', 'created_at']);
            $table->index(['request_id', 'hop_index']);
            $table->index(['is_success', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_forwarding_logs');
    }
};
