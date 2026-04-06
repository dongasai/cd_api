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
        if (Schema::hasTable('coding_usage_logs')) {
            return;
        }
        Schema::create('coding_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->comment('Coding账户ID');
            $table->unsignedBigInteger('channel_id')->nullable()->comment('渠道ID');
            $table->string('request_id', 64)->nullable()->comment('请求ID');

            // 使用量
            $table->unsignedInteger('requests')->default(1)->comment('请求次数');
            $table->unsignedInteger('tokens_input')->default(0)->comment('输入Token数');
            $table->unsignedInteger('tokens_output')->default(0)->comment('输出Token数');
            $table->unsignedInteger('prompts')->default(0)->comment('Prompt次数');
            $table->decimal('credits', 10, 4)->default(0)->comment('消耗积分');
            $table->decimal('cost', 10, 6)->default(0)->comment('金额成本');

            // 模型信息
            $table->string('model', 100)->nullable()->comment('使用的模型');
            $table->decimal('model_multiplier', 5, 2)->default(1.00)->comment('模型消耗倍数');

            // 状态
            $table->enum('status', ['success', 'failed', 'throttled', 'rejected'])->default('success');

            // 元数据
            $table->json('metadata')->nullable()->comment('额外元数据');

            // 时间
            $table->timestamp('created_at')->useCurrent();

            // 索引
            $table->index(['account_id', 'created_at'], 'idx_account_created');
            $table->index(['channel_id', 'created_at'], 'idx_channel_created');
            $table->index('request_id', 'idx_request');
            $table->index(['model', 'created_at'], 'idx_model_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coding_usage_logs');
    }
};
