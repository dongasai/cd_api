<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 搜索驱动配置表迁移
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_drivers')) {
            return;
        }
        Schema::create('search_drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('驱动名称');
            $table->string('slug', 50)->unique()->comment('驱动标识');
            $table->string('driver_class', 200)->comment('驱动类名');
            $table->json('config')->nullable()->comment('驱动配置(JSON)');
            $table->unsignedInteger('timeout')->default(30)->comment('请求超时秒数');
            $table->unsignedInteger('priority')->default(0)->comment('优先级(数字越大优先级越高)');
            $table->boolean('is_default')->default(false)->comment('是否为默认驱动');
            $table->enum('status', ['active', 'inactive', 'error'])->default('active')->comment('状态');
            $table->text('description')->nullable()->comment('描述');
            $table->timestamp('last_used_at')->nullable()->comment('最后使用时间');
            $table->unsignedBigInteger('usage_count')->default(0)->comment('使用次数');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->timestamps();
            $table->softDeletes();

            // 索引
            $table->index('status');
            $table->index('is_default');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_drivers');
    }
};
