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
        Schema::create('coding_accounts', function (Blueprint $table) {
            $table->id();

            // 基本信息
            $table->string('name')->comment('账户名称');
            $table->string('platform', 50)->comment('平台类型: aliyun/volcano/zhipu/github/cursor/custom');

            // 驱动配置
            $table->string('driver_class')->comment('驱动类名');

            // 凭证信息 (加密存储)
            $table->json('credentials')->comment('平台凭证: {api_key, api_secret, access_token}');

            // 状态
            $table->enum('status', ['active', 'warning', 'critical', 'exhausted', 'expired', 'suspended', 'error'])
                ->default('active')
                ->comment('账户状态');

            // 配额配置 (各驱动通用)
            $table->json('quota_config')->nullable()->comment('配额配置: {limits, thresholds, periods}');

            // 配额缓存 (上次同步结果)
            $table->json('quota_cached')->nullable()->comment('缓存的配额信息');

            // 扩展配置
            $table->json('config')->nullable()->comment('驱动特定配置');

            // 同步相关
            $table->timestamp('last_sync_at')->nullable()->comment('最后同步时间');
            $table->text('sync_error')->nullable()->comment('同步错误信息');
            $table->unsignedInteger('sync_error_count')->default(0)->comment('连续同步错误次数');

            // 时间
            $table->timestamp('expires_at')->nullable()->comment('账户过期时间');
            $table->timestamps();

            // 索引
            $table->index('platform', 'idx_platform');
            $table->index('status', 'idx_status');
            $table->index('driver_class', 'idx_driver');
            $table->index('last_sync_at', 'idx_sync');
            $table->index('expires_at', 'idx_coding_accounts_expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coding_accounts');
    }
};
