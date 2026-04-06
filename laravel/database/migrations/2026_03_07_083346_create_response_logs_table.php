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
        if (Schema::hasTable('response_logs')) {
            return;
        }
        Schema::create('response_logs', function (Blueprint $table) {
            $table->id();

            // 关联信息
            $table->unsignedBigInteger('audit_log_id')->comment('关联审计日志 ID');
            $table->string('request_id', 50)->comment('请求唯一标识');
            $table->unsignedBigInteger('request_log_id')->comment('关联请求日志 ID');

            // 响应基础信息
            $table->unsignedSmallInteger('status_code')->comment('HTTP 状态码');
            $table->string('status_message', 100)->nullable()->comment('状态消息');

            // 响应头
            $table->json('headers')->nullable()->comment('响应头');

            // 响应体
            $table->string('content_type', 100)->nullable()->comment('Content-Type');
            $table->unsignedInteger('content_length')->default(0)->comment('响应体长度');
            $table->longText('body_text')->nullable()->comment('响应体文本内容');
            $table->binary('body_binary')->nullable()->comment('响应体二进制内容');

            // 响应内容解析
            $table->string('response_type', 50)->nullable()->comment('响应类型: chat, completion, embedding, error');
            $table->string('finish_reason', 50)->nullable()->comment('完成原因');

            // 生成内容
            $table->longText('generated_text')->nullable()->comment('生成的文本内容');
            $table->json('generated_chunks')->nullable()->comment('流式响应的分块内容');

            // 使用量
            $table->json('usage')->nullable()->comment('Token 使用量详情');

            // 错误信息
            $table->string('error_type', 100)->nullable()->comment('错误类型');
            $table->string('error_code', 50)->nullable()->comment('错误代码');
            $table->text('error_message')->nullable()->comment('错误消息');
            $table->json('error_details')->nullable()->comment('错误详情');

            // 上游信息
            $table->string('upstream_provider', 50)->nullable()->comment('上游提供商');
            $table->string('upstream_model', 100)->nullable()->comment('上游实际模型');
            $table->unsignedInteger('upstream_latency_ms')->default(0)->comment('上游响应延迟');

            // 元数据
            $table->json('metadata')->nullable()->comment('额外元数据');
            $table->timestamp('created_at')->useCurrent();

            // 索引
            $table->index('audit_log_id');
            $table->index('request_log_id');
            $table->index('request_id');
            $table->index(['status_code', 'created_at']);
            $table->index(['upstream_provider', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('response_logs');
    }
};
