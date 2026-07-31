<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 移除 channels 表的旧模型字段（已迁移到 channel_models 表）
     */
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            // model_mappings 字段已在之前的迁移中移除，仅删除 models 和 default_model
            $columns = array_filter(['models', 'default_model', 'model_mappings'], fn ($col) => Schema::hasColumn('channels', $col));
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * 回滚时恢复字段（兼容旧数据）
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->json('models')->nullable()->comment('支持的模型列表（已废弃）');
            $table->string('default_model', 100)->nullable()->comment('默认模型（已废弃）');
        });
    }
};
