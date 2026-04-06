<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建渠道亲和性缓存表
 *
 * 用于缓存渠道亲和性规则的匹配结果，加速请求路由决策
 */
return new class extends Migration
{
    /**
     * 创建 channel_affinity_cache 表
     */
    public function up(): void
    {
        Schema::create('channel_affinity_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('channel_affinity_rules', 'id')->cascadeOnDelete()->comment('规则ID');
            $table->string('key_hash', 64)->comment('Key哈希值');
            $table->foreignId('channel_id')->constrained('channels', 'id')->cascadeOnDelete()->comment('渠道ID');
            $table->string('channel_name')->comment('渠道名称');
            $table->string('key_hint')->nullable()->comment('Key提示');
            $table->unsignedInteger('hit_count')->default(0)->comment('命中次数');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            $table->timestamps();

            // 索引
            $table->unique(['rule_id', 'key_hash'], 'idx_unique_affinity');
            $table->index('expires_at', 'idx_expires');
        });
    }

    /**
     * 删除 channel_affinity_cache 表
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_affinity_cache');
    }
};