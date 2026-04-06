<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建渠道与User-Agent关联表
 *
 * 用于管理渠道的User-Agent访问限制，支持多对多关系
 */
return new class extends Migration
{
    /**
     * 创建 channel_user_agent 中间表
     */
    public function up(): void
    {
        Schema::create('channel_user_agent', function (Blueprint $table) {
            $table->foreignId('channel_id')->constrained('channels', 'id')->cascadeOnDelete()->comment('渠道ID');
            $table->foreignId('user_agent_id')->constrained('user_agents', 'id')->cascadeOnDelete()->comment('User-Agent ID');
            $table->timestamps();

            // 复合主键
            $table->primary(['channel_id', 'user_agent_id']);

            // 索引
            $table->index('channel_id', 'idx_channel_id');
            $table->index('user_agent_id', 'idx_user_agent_id');
        });
    }

    /**
     * 删除 channel_user_agent 表
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_user_agent');
    }
};