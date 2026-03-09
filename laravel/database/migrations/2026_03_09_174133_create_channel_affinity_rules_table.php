<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_affinity_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('规则名称');
            $table->text('description')->nullable()->comment('规则描述');

            $table->json('model_patterns')->nullable()->comment('模型名称正则匹配数组');
            $table->json('path_patterns')->nullable()->comment('请求路径正则匹配数组');
            $table->json('user_agent_patterns')->nullable()->comment('User-Agent 包含匹配数组');

            $table->json('key_sources')->nullable()->comment('Key 来源配置数组');
            $table->string('key_combine_strategy', 20)->default('first')->comment('多 Key 组合策略: first, concat, hash');

            $table->unsignedInteger('ttl_seconds')->default(120)->comment('缓存 TTL（秒）');

            $table->json('param_override_template')->nullable()->comment('参数覆盖模板');
            $table->boolean('skip_retry_on_failure')->default(false)->comment('失败后是否跳过重试');
            $table->boolean('include_group_in_key')->default(false)->comment('是否包含分组在 cache key 中');

            $table->boolean('is_enabled')->default(true)->comment('是否启用');
            $table->unsignedInteger('priority')->default(0)->comment('优先级（数值越大越优先）');

            $table->unsignedBigInteger('hit_count')->default(0)->comment('命中次数统计');
            $table->timestamp('last_hit_at')->nullable()->comment('最后命中时间');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_enabled', 'priority'], 'idx_enabled_priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_affinity_rules');
    }
};
