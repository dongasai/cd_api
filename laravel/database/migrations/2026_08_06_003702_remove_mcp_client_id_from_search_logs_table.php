<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 移除 MCP 相关数据库结构
 */
return new class extends Migration
{
    public function up(): void
    {
        // 删除 search_logs 表的 mcp_client_id 列
        Schema::table('search_logs', function (Blueprint $table) {
            $table->dropColumn('mcp_client_id');
        });

        // 删除 mcp_clients 表
        Schema::dropIfExists('mcp_clients');

        // 删除 system_settings 中 MCP 分组配置
        DB::table('system_settings')->where('group', 'mcp')->delete();
    }

    public function down(): void
    {
        // 恢复 mcp_clients 表
        if (! Schema::hasTable('mcp_clients')) {
            Schema::create('mcp_clients', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('transport')->default('http');
                $table->string('url')->nullable();
                $table->string('command')->nullable();
                $table->json('arguments')->nullable();
                $table->json('env_vars')->nullable();
                $table->text('headers')->nullable();
                $table->boolean('status')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 恢复 search_logs 的 mcp_client_id 列
        Schema::table('search_logs', function (Blueprint $table) {
            $table->string('mcp_client_id')->nullable()->comment('MCP客户端ID')->after('api_key_id');
        });
    }
};
