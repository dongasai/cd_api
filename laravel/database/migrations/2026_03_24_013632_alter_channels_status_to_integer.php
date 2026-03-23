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
            // 先修改为string类型，保留数据
            $table->string('status', 10)->default('active')->change();
        });

        // 将数据转换为整数
        \DB::table('channels')->update([
            'status' => \DB::raw("CASE WHEN status = 'active' THEN '1' ELSE '0' END"),
        ]);

        Schema::table('channels', function (Blueprint $table) {
            // 再修改为integer类型
            $table->unsignedTinyInteger('status')->default(1)->comment('运营状态: 0=禁用, 1=启用')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            // 先修改为string类型
            $table->string('status', 20)->default('active')->change();
        });

        // 将数据转换回字符串
        \DB::table('channels')->update([
            'status' => \DB::raw("CASE WHEN status = '1' THEN 'active' ELSE 'disabled' END"),
        ]);

        Schema::table('channels', function (Blueprint $table) {
            // 恢复为enum类型
            $table->enum('status', ['active', 'disabled', 'maintenance'])->default('active')->comment('运营状态')->change();
        });
    }
};
