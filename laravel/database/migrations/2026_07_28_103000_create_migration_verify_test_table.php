<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 测试迁移：验证迁移管理功能
 *
 * 此迁移用于测试后台迁移管理页面的手动执行功能
 * 测试完成后可删除
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_verify_test', function (Blueprint $table) {
            $table->id();
            $table->string('label')->comment('测试标签');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_verify_test');
    }
};
