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
        if (Schema::hasTable('channel_error_rules')) {
            return;
        }
        Schema::create('channel_error_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('规则名称');
            $table->unsignedBigInteger('coding_account_id')->nullable()->comment('账户级规则');
            $table->string('driver_class')->nullable()->comment('驱动类名（驱动级规则）');
            $table->enum('pattern_type', ['status_code', 'error_message', 'error_type', 'both'])->default('status_code')->comment('匹配类型');
            $table->string('pattern_value')->comment('匹配值');
            $table->enum('pattern_operator', ['exact', 'contains', 'regex'])->default('exact')->comment('匹配方式');
            $table->enum('action', ['pause_account', 'alert_only'])->default('pause_account')->comment('处理动作');
            $table->unsignedInteger('pause_duration_minutes')->default(10)->comment('暂停时长（分钟）');
            $table->unsignedInteger('priority')->default(0)->comment('优先级（越大越优先）');
            $table->boolean('is_enabled')->default(true)->comment('是否启用');
            $table->json('metadata')->nullable()->comment('扩展配置');
            $table->timestamps();

            // 索引
            $table->index(['coding_account_id', 'is_enabled']);
            $table->index(['driver_class', 'is_enabled']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_error_rules');
    }
};
