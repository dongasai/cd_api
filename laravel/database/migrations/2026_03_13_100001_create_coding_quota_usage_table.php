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
        // 创建配额使用量表，用于替代 Redis 存储
        if (Schema::hasTable('coding_quota_usage')) {
            return;
        }
        Schema::create('coding_quota_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->comment('Coding账户ID');

            // 周期标识
            $table->string('metric', 50)->comment('指标名称: prompts, tokens, requests 等');
            $table->string('period_key', 50)->comment('周期标识: Y-m-d, Y-W, Y-m 等');
            $table->string('period_type', 20)->comment('周期类型: 5h, daily, weekly, monthly');

            // 使用量
            $table->unsignedBigInteger('used')->default(0)->comment('已使用量');

            // 周期时间范围
            $table->timestamp('period_starts_at')->nullable()->comment('周期开始时间');
            $table->timestamp('period_ends_at')->nullable()->comment('周期结束时间');

            $table->timestamps();

            // 唯一索引：每个账户在每个周期的每个指标只有一条记录
            $table->unique(['account_id', 'metric', 'period_key'], 'idx_unique_quota');

            // 查询索引
            $table->index(['account_id', 'period_type'], 'idx_quota_account_period_type');
            $table->index(['period_ends_at'], 'idx_quota_period_ends');
        });

        // 创建渠道亲和力缓存表，用于替代 Redis 缓存
        if (! Schema::hasTable('channel_affinity_cache')) {
            Schema::create('channel_affinity_cache', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rule_id')->comment('规则ID');
                $table->string('key_hash', 64)->comment('Key哈希值');

                // 缓存数据
                $table->unsignedBigInteger('channel_id')->comment('渠道ID');
                $table->string('channel_name')->comment('渠道名称');
                $table->string('key_hint')->nullable()->comment('Key提示');
                $table->unsignedInteger('hit_count')->default(0)->comment('命中次数');

                // 过期时间
                $table->timestamp('expires_at')->nullable()->comment('过期时间');

                $table->timestamps();

                // 唯一索引
                $table->unique(['rule_id', 'key_hash'], 'idx_unique_affinity');

                // 过期时间索引
                $table->index(['expires_at'], 'idx_affinity_expires');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_affinity_cache');
        Schema::dropIfExists('coding_quota_usage');
    }
};
