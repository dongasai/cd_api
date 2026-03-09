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
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('API密钥名称');
            $table->string('key_hash')->comment('API密钥哈希');
            $table->string('key_prefix', 20)->comment('API密钥前缀');
            $table->json('permissions')->nullable()->comment('权限配置');
            $table->json('allowed_models')->nullable()->comment('允许的模型列表');
            $table->json('rate_limit')->nullable()->comment('速率限制配置');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            $table->timestamp('last_used_at')->nullable()->comment('最后使用时间');
            $table->enum('status', ['active', 'revoked', 'expired'])->default('active')->comment('状态');
            $table->timestamps();
            $table->softDeletes();

            $table->index('key_prefix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
