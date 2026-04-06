<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 搜索记录表迁移
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query', 500)->comment('搜索查询内容');
            $table->string('driver', 50)->comment('使用的驱动');
            $table->unsignedBigInteger('driver_id')->nullable()->comment('驱动ID');
            $table->unsignedInteger('result_count')->default(0)->comment('返回结果数量');
            $table->unsignedInteger('total_count')->default(0)->comment('总匹配数量');
            $table->boolean('success')->default(true)->comment('是否成功');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->unsignedInteger('response_time_ms')->nullable()->comment('响应时间(毫秒)');
            $table->json('filters')->nullable()->comment('过滤条件');
            $table->json('results')->nullable()->comment('搜索结果摘要(前3条)');
            $table->string('client_ip', 45)->nullable()->comment('客户端IP');
            $table->string('api_key_id')->nullable()->comment('API Key ID');
            $table->string('mcp_client_id')->nullable()->comment('MCP客户端ID');
            $table->timestamp('searched_at')->useCurrent()->comment('搜索时间');

            // 索引
            $table->index('driver');
            $table->index('driver_id');
            $table->index('success');
            $table->index('searched_at');
            $table->index('query');

            // 外键
            $table->foreign('driver_id')->references('id')->on('search_drivers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};