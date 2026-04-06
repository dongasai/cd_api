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
        Schema::create('channel_error_handling_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete()->comment('渠道ID');
            $table->foreignId('account_id')->nullable()->constrained('coding_accounts')->nullOnDelete()->comment('账户ID');
            $table->foreignId('rule_id')->nullable()->constrained('channel_error_rules')->nullOnDelete()->comment('规则ID');
            $table->unsignedSmallInteger('error_status_code')->nullable()->comment('HTTP状态码');
            $table->string('error_type')->nullable()->comment('错误类型');
            $table->text('error_message')->nullable()->comment('错误消息');
            $table->string('action_taken')->default('none')->comment('执行的动作');
            $table->unsignedInteger('pause_duration_minutes')->nullable()->comment('暂停时长');
            $table->string('triggered_by')->default('auto')->comment('触发方式：auto/manual');
            $table->foreignId('user_id')->nullable()->comment('操作用户ID（手动触发时）');
            $table->timestamps();

            // 索引
            $table->index(['channel_id', 'created_at']);
            $table->index(['account_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_error_handling_logs');
    }
};
