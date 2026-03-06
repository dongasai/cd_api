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
        Schema::create('model_multipliers', function (Blueprint $table) {
            $table->id();

            // 匹配规则
            $table->string('platform', 50)->nullable()->comment('适用平台 (null表示通用)');
            $table->string('model_pattern', 100)->comment('模型匹配模式 (支持通配符)');

            // 倍数
            $table->decimal('multiplier', 5, 2)->default(1.00)->comment('消耗倍数');

            // 分类
            $table->string('category', 50)->default('standard')->comment('模型分类: basic/standard/advanced/reasoning');
            $table->string('description', 255)->nullable()->comment('描述');

            // 状态
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0)->comment('优先级 (高优先匹配)');

            // 时间
            $table->timestamps();

            // 索引
            $table->index(['platform', 'model_pattern'], 'idx_platform_pattern');
            $table->index('category', 'idx_category');
            $table->index('is_active', 'idx_active');
        });

        DB::statement("ALTER TABLE model_multipliers COMMENT='模型消耗倍数表'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_multipliers');
    }
};
