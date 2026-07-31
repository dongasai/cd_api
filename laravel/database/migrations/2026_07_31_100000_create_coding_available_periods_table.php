<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coding_available_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coding_account_id')->comment('关联 Coding 账户 ID');
            $table->time('start_time')->comment('开始时间，如 09:00:00');
            $table->time('end_time')->comment('结束时间，如 12:00:00');
            $table->json('weekdays')->nullable()->comment('适用星期，如 [1,2,3,4,5] 表示周一到周五，null 表示每天');
            $table->boolean('is_enabled')->default(true)->comment('是否启用');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->foreign('coding_account_id')
                ->references('id')
                ->on('coding_accounts')
                ->cascadeOnDelete();

            $table->index('coding_account_id', 'idx_account_id');
            $table->index(['coding_account_id', 'is_enabled'], 'idx_account_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coding_available_periods');
    }
};
