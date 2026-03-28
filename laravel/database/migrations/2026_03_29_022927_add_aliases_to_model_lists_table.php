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
            // 添加 aliases JSON 字段，用于存储模型别名列表
            $table->json('aliases')
                ->nullable()
                ->after('common_name')
                ->comment('模型别名列表，用于路由降级和模型匹配');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_lists', function (Blueprint $table) {
            $table->dropColumn('aliases');
        });
    }
};
