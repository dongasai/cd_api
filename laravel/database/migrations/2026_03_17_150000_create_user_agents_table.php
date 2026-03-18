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
        // 创建user_agents表
        Schema::create('user_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('规则名称');
            $table->json('patterns')->comment('正则表达式数组');
            $table->text('description')->nullable()->comment('规则描述');
            $table->boolean('is_enabled')->default(true)->comment('是否启用');
            $table->unsignedBigInteger('hit_count')->default(0)->comment('命中次数');
            $table->timestamp('last_hit_at')->nullable()->comment('最后命中时间');
            $table->timestamps();

            $table->index('is_enabled', 'idx_enabled');
        });

        // 创建channel_user_agent中间表
        Schema::create('channel_user_agent', function (Blueprint $table) {
            $table->unsignedBigInteger('channel_id')->comment('渠道ID');
            $table->unsignedBigInteger('user_agent_id')->comment('User-Agent ID');
            $table->timestamps();

            $table->primary(['channel_id', 'user_agent_id']);
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('user_agent_id')->references('id')->on('user_agents')->onDelete('cascade');

            $table->index('channel_id', 'idx_channel_id');
            $table->index('user_agent_id', 'idx_user_agent_id');
        });

        // 修改channels表，添加标志字段
        Schema::table('channels', function (Blueprint $table) {
            $table->boolean('has_user_agent_restriction')->default(false)->comment('是否有UA限制');
            $table->index('has_user_agent_restriction', 'idx_has_ua_restriction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropIndex('idx_has_ua_restriction');
            $table->dropColumn('has_user_agent_restriction');
        });

        Schema::dropIfExists('channel_user_agent');
        Schema::dropIfExists('user_agents');
    }
};
