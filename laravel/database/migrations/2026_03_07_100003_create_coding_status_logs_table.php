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
        if (Schema::hasTable('coding_status_logs')) {
            return;
        }
        Schema::create('coding_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->comment('Coding账户ID');
            $table->unsignedBigInteger('channel_id')->nullable()->comment('关联渠道ID');

            // 状态变更
            $table->string('from_status', 20)->comment('原状态');
            $table->string('to_status', 20)->comment('新状态');
            $table->string('reason', 255)->nullable()->comment('变更原因');

            // 配额信息 (变更时快照)
            $table->json('quota_snapshot')->nullable()->comment('配额快照');

            // 触发方式
            $table->enum('triggered_by', ['system', 'manual', 'api', 'sync'])->default('system')->comment('触发方式');
            $table->unsignedBigInteger('user_id')->nullable()->comment('操作用户 (手动触发时)');

            // 时间
            $table->timestamp('created_at')->useCurrent();

            // 索引
            $table->index(['account_id', 'created_at'], 'idx_status_logs_account_created');
            $table->index(['channel_id', 'created_at'], 'idx_status_logs_channel_created');
            $table->index(['from_status', 'to_status'], 'idx_status_logs_status_change');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coding_status_logs');
    }
};
