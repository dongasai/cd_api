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
        Schema::table('channel_request_logs', function (Blueprint $table) {
            // 添加流式响应块记录字段
            $table->longText('response_body_chunks')->nullable()->after('response_body')->comment('流式响应块内容');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channel_request_logs', function (Blueprint $table) {
            $table->dropColumn('response_body_chunks');
        });
    }
};
