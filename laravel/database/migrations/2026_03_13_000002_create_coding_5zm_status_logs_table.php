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
        Schema::create('coding_5zm_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('coding_accounts')->cascadeOnDelete()->comment('Coding账户ID');
            $table->foreignId('channel_id')->nullable()->constrained('channels')->nullOnDelete()->comment('关联渠道ID');

            // 状态变更
            $table->string('from_status', 20)->comment('原状态');
            $table->string('to_status', 20)->comment('新状态');
            $table->string('reason')->nullable()->comment('变更原因');

            // 三维度配额快照
            $table->unsignedInteger('quota_5h_used')->default(0)->comment('5小时周期已用');
            $table->unsignedInteger('quota_5h_limit')->default(0)->comment('5小时周期限额');
            $table->decimal('quota_5h_rate', 5, 4)->default(0)->comment('5小时周期使用率');

            $table->unsignedInteger('quota_weekly_used')->default(0)->comment('周已用');
            $table->unsignedInteger('quota_weekly_limit')->default(0)->comment('周限额');
            $table->decimal('quota_weekly_rate', 5, 4)->default(0)->comment('周使用率');

            $table->unsignedInteger('quota_monthly_used')->default(0)->comment('月已用');
            $table->unsignedInteger('quota_monthly_limit')->default(0)->comment('月限额');
            $table->decimal('quota_monthly_rate', 5, 4)->default(0)->comment('月使用率');

            // 触发信息
            $table->enum('triggered_by', ['system', 'manual', 'api', 'sync', 'quota_exhausted', 'quota_recovered'])->default('system')->comment('触发方式');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('操作用户');

            // 周期信息
            $table->string('period_5h', 20)->nullable()->comment('5小时周期标识');
            $table->string('period_weekly', 10)->nullable()->comment('周周期标识');
            $table->string('period_monthly', 7)->nullable()->comment('月周期标识');

            $table->timestamp('created_at')->useCurrent()->comment('创建时间');

            // 索引
            $table->index(['account_id', 'created_at'], 'idx_account_created');
            $table->index(['channel_id', 'created_at'], 'idx_channel_created');
            $table->index(['from_status', 'to_status'], 'idx_status_change');
            $table->index(['triggered_by', 'created_at'], 'idx_triggered');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coding_5zm_status_logs');
    }
};
