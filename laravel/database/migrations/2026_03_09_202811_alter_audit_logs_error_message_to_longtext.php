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
        Schema::table('audit_logs', function (Blueprint $table) {
            // 将 error_message 从 text 改为 longText，以支持存储更长的错误信息
            $table->longText('error_message')->nullable()->change()->comment('错误信息');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->text('error_message')->nullable()->change()->comment('错误信息');
        });
    }
};
