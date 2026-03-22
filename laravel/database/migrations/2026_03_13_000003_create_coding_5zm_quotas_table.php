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
        // 创建 Request5ZM 驱动的配额表
        Schema::create('coding_5zm_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained('coding_accounts')->cascadeOnDelete()->comment('Coding账户ID');

            // 配额限制
            $table->unsignedInteger('limit_5h')->default(300)->comment('5小时周期限额');
            $table->unsignedInteger('limit_weekly')->default(1000)->comment('周限额');
            $table->unsignedInteger('limit_monthly')->default(5000)->comment('月限额');

            // 当前使用量
            $table->unsignedInteger('used_5h')->default(0)->comment('5小时周期已用');
            $table->unsignedInteger('used_weekly')->default(0)->comment('周已用');
            $table->unsignedInteger('used_monthly')->default(0)->comment('月已用');

            // 周期标识
            $table->string('period_5h', 20)->nullable()->comment('当前5小时周期标识');
            $table->string('period_weekly', 10)->nullable()->comment('当前周周期标识');
            $table->string('period_monthly', 7)->nullable()->comment('当前月周期标识');

            // 阈值配置
            $table->decimal('threshold_warning', 4, 3)->default(0.800)->comment('警告阈值');
            $table->decimal('threshold_critical', 4, 3)->default(0.900)->comment('临界阈值');
            $table->decimal('threshold_disable', 4, 3)->default(0.950)->comment('禁用阈值');

            // 周期配置
            $table->unsignedSmallInteger('period_offset')->default(0)->comment('5小时周期偏移量(秒)');
            $table->unsignedTinyInteger('reset_day')->default(1)->comment('月重置日期');

            // 同步信息
            $table->timestamp('last_sync_at')->nullable()->comment('最后同步时间');
            $table->timestamp('last_usage_at')->nullable()->comment('最后消耗时间');

            $table->timestamps();

            // 索引
            $table->index(['period_5h', 'account_id'], 'idx_5zm_quotas_period_5h');
            $table->index(['period_weekly', 'account_id'], 'idx_5zm_quotas_period_weekly');
            $table->index(['period_monthly', 'account_id'], 'idx_5zm_quotas_period_monthly');
        });

        // 从 coding_accounts 表移除配额相关字段
        Schema::table('coding_accounts', function (Blueprint $table) {
            $table->dropColumn(['quota_config', 'quota_cached']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 恢复 coding_accounts 表的配额字段
        Schema::table('coding_accounts', function (Blueprint $table) {
            $table->json('quota_config')->nullable()->comment('配额配置: {limits, thresholds, periods}')->after('status');
            $table->json('quota_cached')->nullable()->comment('缓存的配额信息')->after('quota_config');
        });

        // 删除 Request5ZM 配额表
        Schema::dropIfExists('coding_5zm_quotas');
    }
};
