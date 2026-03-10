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
            $table->unsignedBigInteger('audit_log_id')->nullable()->comment('关联审计日志 ID')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channel_request_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('audit_log_id')->nullable(false)->comment('关联审计日志 ID')->change();
        });
    }
};
