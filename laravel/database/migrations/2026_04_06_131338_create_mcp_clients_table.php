<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MCP 客户端表迁移
 *
 * 存储外部 MCP Server 的连接配置信息
 */
return new class extends Migration
{
    /**
     * 创建 mcp_clients 表
     */
    public function up(): void
    {
        if (Schema::hasTable('mcp_clients')) {
            return;
        }
        Schema::create('mcp_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('客户端名称');
            $table->string('slug', 100)->unique()->comment('标识符');
            $table->enum('transport', ['stdio', 'http'])->default('http')->comment('传输协议');
            $table->string('url', 500)->nullable()->comment('HTTP+SSE URL');
            $table->string('command', 500)->nullable()->comment('stdio 命令');
            $table->json('args')->nullable()->comment('stdio 参数');
            $table->json('headers')->nullable()->comment('HTTP 请求头');
            $table->unsignedInteger('timeout')->default(30)->comment('连接超时秒数');
            $table->enum('status', ['active', 'inactive', 'error'])->default('inactive')->comment('状态');
            $table->timestamp('last_connected_at')->nullable()->comment('最后连接时间');
            $table->text('connection_error')->nullable()->comment('连接错误信息');
            $table->json('capabilities')->nullable()->comment('服务器能力列表');
            $table->text('description')->nullable()->comment('描述');
            $table->timestamps();
            $table->softDeletes();

            // 索引
            $table->index('status');
            $table->index('transport');
        });
    }

    /**
     * 删除 mcp_clients 表
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_clients');
    }
};
