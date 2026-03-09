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
        Schema::table('model_lists', function (Blueprint $table) {
            $table->string('hugging_face_id', 100)->nullable()->after('provider')->comment('Hugging Face 模型ID');
            $table->string('common_name', 100)->nullable()->after('hugging_face_id')->comment('通用名字');
            $table->decimal('pricing_prompt', 10, 6)->nullable()->after('context_length')->comment('输入价格 (每百万token)');
            $table->decimal('pricing_completion', 10, 6)->nullable()->after('pricing_prompt')->comment('输出价格 (每百万token)');
            $table->decimal('pricing_input_cache_read', 10, 6)->nullable()->after('pricing_completion')->comment('缓存读取价格 (每百万token)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_lists', function (Blueprint $table) {
            $table->dropColumn(['hugging_face_id', 'common_name', 'pricing_prompt', 'pricing_completion', 'pricing_input_cache_read']);
        });
    }
};
