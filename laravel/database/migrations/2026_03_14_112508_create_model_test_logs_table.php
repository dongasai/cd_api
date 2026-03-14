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
        Schema::create('model_test_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('test_type', ['channel_direct', 'system_api'])->comment('测试类型');
            $table->unsignedBigInteger('channel_id')->nullable()->comment('渠道ID');
            $table->string('channel_name', 100)->nullable()->comment('渠道名称');
            $table->string('model', 100)->comment('测试模型');
            $table->string('actual_model', 100)->nullable()->comment('实际上游模型');
            $table->unsignedBigInteger('api_key_id')->nullable()->comment('API Key ID');
            $table->string('api_key_name', 100)->nullable()->comment('API Key名称');
            $table->unsignedBigInteger('prompt_preset_id')->nullable()->comment('关联提示词ID');
            $table->text('system_prompt')->nullable()->comment('系统提示词');
            $table->text('user_message')->nullable()->comment('用户消息');
            $table->longText('assistant_response')->nullable()->comment('AI响应');
            $table->json('request_headers')->nullable()->comment('请求头部');
            $table->boolean('is_stream')->default(false)->comment('是否流式');
            $table->unsignedInteger('response_time_ms')->nullable()->comment('响应时间（毫秒）');
            $table->unsignedInteger('first_token_ms')->nullable()->comment('首token时间');
            $table->unsignedInteger('prompt_tokens')->nullable()->comment('输入token');
            $table->unsignedInteger('completion_tokens')->nullable()->comment('输出token');
            $table->unsignedInteger('total_tokens')->nullable()->comment('总token');
            $table->enum('status', ['success', 'failed', 'timeout'])->default('success')->comment('状态');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->json('metadata')->nullable()->comment('元数据');
            $table->timestamp('created_at')->useCurrent()->comment('创建时间');

            // 索引
            $table->index(['test_type', 'created_at']);
            $table->index(['channel_id', 'created_at']);
            $table->index(['api_key_id', 'created_at']);
            $table->index(['model', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_test_logs');
    }
};
