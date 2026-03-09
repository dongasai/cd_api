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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // 用户信息
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户 ID');
            $table->string('username', 100)->nullable()->comment('用户名（冗余存储）');

            // API密钥信息
            $table->unsignedBigInteger('api_key_id')->nullable()->comment('API密钥 ID');
            $table->string('api_key_name', 100)->nullable()->comment('API密钥名称（冗余存储）');
            $table->string('cached_key_prefix', 50)->nullable()->comment('缓存的API密钥前缀');

            // 渠道信息
            $table->unsignedBigInteger('channel_id')->nullable()->comment('渠道 ID');
            $table->string('channel_name', 100)->nullable()->comment('渠道名称（冗余存储）');

            // 请求标识
            $table->string('request_id', 50)->comment('请求唯一标识');
            $table->unsignedTinyInteger('request_type')->default(1)->comment('请求类型: 1=聊天, 2=补全, 3=嵌入, 4=其他');

            // 模型信息
            $table->string('model', 100)->nullable()->comment('请求的模型');
            $table->string('actual_model', 100)->nullable()->comment('实际使用的模型');

            // Token 使用
            $table->unsignedInteger('prompt_tokens')->default(0)->comment('输入 token 数');
            $table->unsignedInteger('completion_tokens')->default(0)->comment('输出 token 数');
            $table->unsignedInteger('total_tokens')->default(0)->comment('总 token 数');
            $table->unsignedInteger('cache_read_tokens')->default(0)->comment('缓存读取 token 数');
            $table->unsignedInteger('cache_write_tokens')->default(0)->comment('缓存写入 token 数');

            // 成本与配额
            $table->decimal('cost', 10, 6)->default(0)->comment('成本 (美元)');
            $table->decimal('quota', 10, 6)->default(0)->comment('配额消耗点数');
            $table->string('billing_source', 50)->default('wallet')->comment('计费来源: wallet, quota, trial');

            // 响应信息
            $table->unsignedSmallInteger('status_code')->nullable()->comment('HTTP 状态码');
            $table->unsignedInteger('latency_ms')->default(0)->comment('总响应延迟 (毫秒)');
            $table->unsignedInteger('first_token_ms')->default(0)->comment('首 token 延迟 (毫秒)');
            $table->boolean('is_stream')->default(false)->comment('是否流式请求');
            $table->string('finish_reason', 50)->nullable()->comment('结束原因: stop, length, content_filter');

            // 错误信息
            $table->string('error_type', 100)->nullable()->comment('错误类型');
            $table->text('error_message')->nullable()->comment('错误信息');

            // 客户端信息
            $table->string('client_ip', 45)->nullable()->comment('客户端 IP');
            $table->string('user_agent', 500)->nullable()->comment('User Agent');

            // 分组与路由
            $table->string('group_name', 50)->nullable()->comment('分组名称');
            $table->json('channel_affinity')->nullable()->comment('渠道亲和性规则');

            // 扩展信息
            $table->json('metadata')->nullable()->comment('额外元数据');
            $table->timestamps();

            // 索引
            $table->index(['user_id', 'created_at']);
            $table->index(['api_key_id', 'created_at']);
            $table->index('cached_key_prefix');
            $table->index(['channel_id', 'created_at']);
            $table->index('request_id');
            $table->index(['model', 'created_at']);
            $table->index(['status_code', 'created_at']);
            $table->index(['group_name', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
