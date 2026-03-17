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
            $table->string('source_protocol', 20)->nullable()->after('actual_model')->comment('请求格式/源协议: openai, anthropic');
            $table->string('target_protocol', 20)->nullable()->after('source_protocol')->comment('上游格式/目标协议: openai, anthropic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['source_protocol', 'target_protocol']);
        });
    }
};
