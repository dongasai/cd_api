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
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn([
                'models',
                'default_model',
                'model_mappings',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->json('models')->nullable()->comment('支持的模型列表');
            $table->string('default_model')->nullable()->comment('默认模型');
            $table->json('model_mappings')->nullable()->comment('模型映射配置');
        });
    }
};
