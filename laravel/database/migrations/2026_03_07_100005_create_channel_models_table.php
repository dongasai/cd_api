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
        if (Schema::hasTable('channel_models')) {
            return;
        }
        Schema::create('channel_models', function (Blueprint $table) {
            $table->id();

            // 关联渠道
            $table->unsignedBigInteger('channel_id')->comment('关联的渠道 ID');

            // 模型信息
            $table->string('model_name')->comment('模型名称，如 gpt-4、claude-3-opus');
            $table->string('display_name')->nullable()->comment('显示名称');

            // 模型映射
            $table->string('mapped_model')->nullable()->comment('映射到渠道的模型名称');

            // 状态配置
            $table->boolean('is_default')->default(false)->comment('是否为默认模型');
            $table->boolean('is_enabled')->default(true)->comment('是否启用');

            // 限制配置
            $table->unsignedInteger('rpm_limit')->nullable()->comment('每分钟请求限制');
            $table->unsignedInteger('context_length')->nullable()->comment('上下文长度');

            // 消耗倍率
            $table->decimal('multiplier', 8, 4)->default(1.0000)->comment('消耗倍率');

            // 额外配置
            $table->json('config')->nullable()->comment('JSON 格式的额外配置');

            $table->timestamps();

            // 索引
            $table->unique(['channel_id', 'model_name']);
            $table->index(['channel_id', 'is_enabled']);
            $table->index(['channel_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_models');
    }
};
