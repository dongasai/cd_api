<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->comment('操作类型');
            $table->string('target', 30)->comment('操作对象');
            $table->unsignedBigInteger('target_id')->nullable()->comment('对象ID');
            $table->string('target_name', 255)->nullable()->comment('对象名称(冗余)');
            $table->string('source', 20)->default('admin')->comment('操作来源: admin/schedule/system/api');
            $table->unsignedBigInteger('user_id')->nullable()->comment('操作用户ID');
            $table->string('username', 100)->nullable()->comment('用户名(冗余)');
            $table->string('description', 500)->nullable()->comment('操作描述');
            $table->string('reason', 500)->nullable()->comment('操作原因');
            $table->json('before_data')->nullable()->comment('操作前数据');
            $table->json('after_data')->nullable()->comment('操作后数据');
            $table->string('ip', 45)->nullable()->comment('操作IP');
            $table->string('user_agent', 500)->nullable()->comment('User Agent');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'created_at'], 'idx_type_created');
            $table->index(['target', 'target_id'], 'idx_target');
            $table->index(['source', 'created_at'], 'idx_source_created');
            $table->index(['user_id', 'created_at'], 'idx_user_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};
