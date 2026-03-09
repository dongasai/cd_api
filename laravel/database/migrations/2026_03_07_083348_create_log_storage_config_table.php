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
        Schema::create('log_storage_config', function (Blueprint $table) {
            $table->id();

            // 配置范围
            $table->enum('scope', ['global', 'api_key'])->default('global')->comment('配置范围');
            $table->unsignedBigInteger('scope_id')->nullable()->comment('范围 ID (api_key_id)');

            // 存储策略
            $table->boolean('store_request_body')->default(true)->comment('是否存储请求体');
            $table->boolean('store_response_body')->default(true)->comment('是否存储响应体');
            $table->boolean('store_headers')->default(true)->comment('是否存储请求头');
            $table->boolean('store_messages')->default(true)->comment('是否存储消息内容');
            $table->boolean('store_generated_text')->default(true)->comment('是否存储生成文本');

            // 敏感信息处理
            $table->boolean('mask_sensitive')->default(true)->comment('是否脱敏敏感信息');
            $table->json('sensitive_patterns')->nullable()->comment('自定义敏感词模式');

            // 存储限制
            $table->unsignedInteger('max_body_length')->default(65535)->comment('最大存储长度');
            $table->decimal('sample_rate', 5, 4)->default(1.0000)->comment('采样率 (0-1)');

            // 保留策略
            $table->unsignedInteger('retention_days')->default(30)->comment('保留天数');
            $table->boolean('archive_enabled')->default(false)->comment('是否归档');
            $table->string('archive_storage', 50)->default('database')->comment('归档存储位置');

            // 元数据
            $table->string('name', 100)->comment('配置名称');
            $table->text('description')->nullable()->comment('配置描述');
            $table->boolean('is_default')->default(false)->comment('是否默认配置');
            $table->timestamps();
            $table->softDeletes();

            // 索引
            $table->index(['scope', 'scope_id']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_storage_config');
    }
};
