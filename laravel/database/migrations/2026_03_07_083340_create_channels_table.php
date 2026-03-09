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
        Schema::create('channels', function (Blueprint $table) {
            $table->id();

            // 继承关系
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父渠道 ID');
            $table->enum('inherit_mode', ['merge', 'override', 'extend'])->default('merge')->comment('继承模式');

            // 基础信息
            $table->string('name')->comment('渠道名称');
            $table->string('slug', 100)->nullable()->comment('渠道标识');
            $table->string('provider', 50)->comment('提供商类型');

            // 连接配置 (可继承)
            $table->string('base_url', 500)->nullable()->comment('API 基础 URL');
            $table->text('api_key')->nullable()->comment('加密的 API Key');
            $table->string('api_key_hash', 64)->nullable()->comment('API Key 指纹 (SHA256前8位)');

            // 模型配置 (可继承)
            $table->json('models')->nullable()->comment('支持的模型列表');
            $table->string('default_model', 100)->nullable()->comment('默认模型(用于测试)');

            // 负载均衡
            $table->unsignedInteger('weight')->default(1)->comment('负载均衡权重 (1-100)');
            $table->unsignedInteger('priority')->default(1)->comment('优先级 (越小越优先)');

            // 状态管理
            $table->enum('status', ['active', 'disabled', 'maintenance'])->default('active')->comment('运营状态');
            $table->enum('health_status', ['healthy', 'unhealthy', 'unknown'])->default('unknown')->comment('健康状态');
            $table->unsignedInteger('failure_count')->default(0)->comment('连续失败次数');
            $table->unsignedInteger('success_count')->default(0)->comment('连续成功次数');

            // 时间记录
            $table->timestamp('last_check_at')->nullable()->comment('最后健康检查时间');
            $table->timestamp('last_failure_at')->nullable()->comment('最后失败时间');
            $table->timestamp('last_success_at')->nullable()->comment('最后成功时间');

            // 统计信息 (冗余存储)
            $table->unsignedBigInteger('total_requests')->default(0)->comment('总请求数');
            $table->unsignedBigInteger('total_tokens')->default(0)->comment('总 Token 数');
            $table->decimal('total_cost', 12, 6)->default(0)->comment('总成本');
            $table->unsignedInteger('avg_latency_ms')->default(0)->comment('平均延迟');
            $table->decimal('success_rate', 5, 4)->default(1.0000)->comment('成功率');

            // 高级配置 (可继承)
            $table->json('config')->nullable()->comment('额外配置');

            // 元数据
            $table->text('description')->nullable()->comment('渠道描述');
            $table->timestamps();
            $table->softDeletes();

            // 索引
            $table->index('parent_id');
            $table->index('slug');
            $table->index(['provider', 'status']);
            $table->index(['status', 'health_status']);
            $table->index(['status', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
