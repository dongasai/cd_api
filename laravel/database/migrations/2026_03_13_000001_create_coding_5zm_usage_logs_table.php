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
        Schema::create('coding_5zm_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('coding_accounts')->cascadeOnDelete()->comment('Coding账户ID');
            $table->foreignId('channel_id')->nullable()->constrained('channels')->nullOnDelete()->comment('渠道ID');
            $table->string('request_id', 64)->nullable()->index()->comment('请求ID');

            // 请求信息
            $table->unsignedInteger('requests')->default(1)->comment('请求次数');
            $table->string('model', 100)->nullable()->comment('使用的模型');
            $table->decimal('model_multiplier', 5, 2)->default(1.00)->comment('模型消耗倍数');

            // 周期标识
            $table->string('period_5h', 20)->comment('5小时周期标识(如: 2026-03-13-0)');
            $table->string('period_weekly', 10)->comment('周周期标识(如: 2026-11)');
            $table->string('period_monthly', 7)->comment('月周期标识(如: 2026-03)');

            // 配额快照（消耗前）
            $table->unsignedInteger('quota_before_5h')->default(0)->comment('消耗前5小时配额');
            $table->unsignedInteger('quota_before_weekly')->default(0)->comment('消耗前周配额');
            $table->unsignedInteger('quota_before_monthly')->default(0)->comment('消耗前月配额');

            // 配额快照（消耗后）
            $table->unsignedInteger('quota_after_5h')->default(0)->comment('消耗后5小时配额');
            $table->unsignedInteger('quota_after_weekly')->default(0)->comment('消耗后周配额');
            $table->unsignedInteger('quota_after_monthly')->default(0)->comment('消耗后月配额');

            // 状态和元数据
            $table->enum('status', ['success', 'failed', 'throttled', 'rejected'])->default('success')->comment('状态');
            $table->json('metadata')->nullable()->comment('额外元数据');
            $table->timestamp('created_at')->useCurrent()->comment('创建时间');

            // 索引
            $table->index(['account_id', 'created_at'], 'idx_account_created');
            $table->index(['channel_id', 'created_at'], 'idx_channel_created');
            $table->index(['period_5h', 'account_id'], 'idx_period_5h');
            $table->index(['period_weekly', 'account_id'], 'idx_period_weekly');
            $table->index(['period_monthly', 'account_id'], 'idx_period_monthly');
            $table->index(['model', 'created_at'], 'idx_model_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coding_5zm_usage_logs');
    }
};
