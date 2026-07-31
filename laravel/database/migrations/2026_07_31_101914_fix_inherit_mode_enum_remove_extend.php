<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 修复 inherit_mode enum：移除未实现的 'extend' 值，与 PHP InheritMode 枚举保持一致
     */
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->enum('inherit_mode', ['merge', 'override'])->default('merge')->comment('继承模式')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->enum('inherit_mode', ['merge', 'override', 'extend'])->default('merge')->comment('继承模式')->change();
        });
    }
};
