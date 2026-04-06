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
        if (Schema::hasTable('channel_request_logs')) {
            return;
        }
        Schema::create('channel_request_logs', function (Blueprint $table) {
            $table->id();

            // 关联信息
            $table->unsignedBigInteger('audit_log_id')->comment('关联审计日志 ID');
            $table->unsignedBigInteger('request_log_id')->nullable()->comment('关联请求日志 ID');
            $table->string('request_id', 50)->comment('请求唯一标识');

            // 渠道信息
            $table->unsignedBigInteger('channel_id')->comment('渠道 ID');
            $table->string('channel_name', 100)->nullable()->comment('渠道名称 (冗余)');
            $table->string('provider', 50)->nullable()->comment('渠道提供商');

            // 请求详情
            $table->string('method', 10)->default('POST')->comment('HTTP 方法');
            $table->string('path', 500)->comment('请求路径');
            $table->string('base_url', 500)->nullable()->comment('渠道 Base URL');
            $table->string('full_url', 1000)->nullable()->comment('完整请求 URL');

            // 请求头
            $table->json('request_headers')->nullable()->comment('请求头');

            // 请求体
            $table->longText('request_body')->nullable()->comment('请求体内容');
            $table->unsignedInteger('request_size')->default(0)->comment('请求体大小 (字节)');

            // 响应信息
            $table->unsignedSmallInteger('response_status')->nullable()->comment('响应状态码');
            $table->json('response_headers')->nullable()->comment('响应头');
            $table->longText('response_body')->nullable()->comment('响应体内容');
            $table->unsignedInteger('response_size')->default(0)->comment('响应体大小 (字节)');

            // 性能指标
            $table->unsignedInteger('latency_ms')->default(0)->comment('请求延迟 (毫秒)');
            $table->unsignedInteger('ttfb_ms')->default(0)->comment('首字节时间 (毫秒)');

            // 请求结果
            $table->boolean('is_success')->default(false)->comment('是否成功');
            $table->string('error_type', 100)->nullable()->comment('错误类型');
            $table->text('error_message')->nullable()->comment('错误消息');

            // Token 使用情况
            $table->json('usage')->nullable()->comment('Token 使用详情');

            // 元数据
            $table->json('metadata')->nullable()->comment('额外元数据');
            $table->timestamp('sent_at')->nullable()->comment('发送时间');
            $table->timestamps();

            // 索引
            $table->index('audit_log_id');
            $table->index('request_log_id');
            $table->index('request_id');
            $table->index('channel_id');
            $table->index(['channel_id', 'created_at']);
            $table->index(['is_success', 'created_at']);
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_request_logs');
    }
};
