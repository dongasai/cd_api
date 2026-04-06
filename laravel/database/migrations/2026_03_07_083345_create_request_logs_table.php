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
        if (Schema::hasTable('request_logs')) {
            return;
        }
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();

            // 关联信息
            $table->unsignedBigInteger('audit_log_id')->comment('关联审计日志 ID');
            $table->string('request_id', 50)->comment('请求唯一标识');

            // 请求基础信息
            $table->string('method', 10)->comment('HTTP 方法');
            $table->string('path', 500)->comment('请求路径');
            $table->text('query_string')->nullable()->comment('URL 查询参数');

            // 请求头
            $table->json('headers')->nullable()->comment('请求头 (脱敏处理)');

            // 请求体
            $table->string('content_type', 100)->nullable()->comment('Content-Type');
            $table->unsignedInteger('content_length')->default(0)->comment('请求体长度');
            $table->longText('body_text')->nullable()->comment('请求体文本内容');
            $table->binary('body_binary')->nullable()->comment('请求体二进制内容 (如图片)');

            // 模型请求参数
            $table->string('model', 100)->nullable()->comment('请求模型');
            $table->json('model_params')->nullable()->comment('模型参数 (temperature, max_tokens 等)');
            $table->json('messages')->nullable()->comment('聊天消息列表');
            $table->text('prompt')->nullable()->comment('提示词 (completion 接口)');

            // 敏感信息处理
            $table->json('sensitive_fields')->nullable()->comment('已脱敏的字段列表');
            $table->boolean('has_sensitive')->default(false)->comment('是否包含敏感信息');

            // 元数据
            $table->json('metadata')->nullable()->comment('额外元数据');
            $table->timestamp('created_at')->useCurrent();

            // 索引
            $table->index('audit_log_id');
            $table->index('request_id');
            $table->index(['model', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};
