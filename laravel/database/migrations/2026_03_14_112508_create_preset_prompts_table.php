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
        Schema::create('preset_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('提示词名称');
            $table->string('category', 50)->comment('分类');
            $table->text('content')->comment('提示词内容');
            $table->json('variables')->nullable()->comment('变量模板');
            $table->json('headers')->nullable()->comment('预设HTTP头部信息');
            $table->boolean('is_enabled')->default(true)->comment('是否启用');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            // 索引
            $table->index(['category', 'is_enabled']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preset_prompts');
    }
};
