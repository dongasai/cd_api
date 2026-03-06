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
        // api_keys - 添加软删除
        Schema::table('api_keys', function (Blueprint $table) {
            $table->softDeletes();
        });

        // channels - 添加软删除
        Schema::table('channels', function (Blueprint $table) {
            $table->softDeletes();
        });

        // channel_groups - 添加软删除
        Schema::table('channel_groups', function (Blueprint $table) {
            $table->softDeletes();
        });

        // channel_tags - 添加 updated_at 和 deleted_at
        Schema::table('channel_tags', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->softDeletes();
        });

        // channel_tag_pivot - 添加时间戳
        Schema::table('channel_tag_pivot', function (Blueprint $table) {
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
        });

        // model_mappings - 添加软删除
        Schema::table('model_mappings', function (Blueprint $table) {
            $table->softDeletes();
        });

        // audit_logs - 添加 updated_at (日志表通常不需要软删除)
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        // channel_forwarding_logs - 添加 updated_at (日志表通常不需要软删除)
        Schema::table('channel_forwarding_logs', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        // log_storage_config - 添加软删除
        Schema::table('log_storage_config', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('channel_groups', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('channel_tags', function (Blueprint $table) {
            $table->dropColumn(['updated_at', 'deleted_at']);
        });

        Schema::table('channel_tag_pivot', function (Blueprint $table) {
            $table->dropColumn(['created_at', 'updated_at', 'deleted_at']);
        });

        Schema::table('model_mappings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });

        Schema::table('channel_forwarding_logs', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });

        Schema::table('log_storage_config', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
