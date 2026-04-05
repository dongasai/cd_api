<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('response_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('response_id', 255)->unique()->comment('当前响应 ID');
            $table->string('previous_response_id', 255)->nullable()->comment('上一次响应 ID（用于追溯对话链）');
            $table->unsignedBigInteger('api_key_id')->nullable()->comment('API Key ID');

            // 核心数据
            $table->json('messages')->comment('完整消息历史');
            $table->string('model', 100)->comment('模型名称');

            // 元数据
            $table->unsignedInteger('total_tokens')->default(0)->comment('总 Token 消耗');
            $table->unsignedInteger('message_count')->default(0)->comment('消息数量');

            // 时间管理
            $table->timestamp('expires_at')->comment('过期时间');
            $table->timestamps();

            // 索引
            // 注意：response_id 已有 unique 索引，不需要额外 index
            $table->index('previous_response_id'); // 用于追溯对话链
            $table->index(['api_key_id', 'expires_at']); // 用于清理和查询
            $table->index('expires_at'); // 用于过期清理

            // 外键（如果 api_keys 表存在）
            // $table->foreign('api_key_id')->references('id')->on('api_keys')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('response_sessions');
    }
};
