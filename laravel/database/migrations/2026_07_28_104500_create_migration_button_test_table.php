<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 测试迁移：验证按钮显示
 *
 * 此迁移用于测试迁移管理页面的按钮显示逻辑
 * 测试完成后可删除
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_button_test', function (Blueprint $table) {
            $table->id();
            $table->string('test_field')->comment('测试字段');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_button_test');
    }
};