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
            $table->unsignedBigInteger('coding_account_id')->nullable()->after('config')->comment('关联Coding账户ID');
            $table->json('coding_status_override')->nullable()->after('coding_account_id')->comment('渠道级别的Coding状态覆盖配置');

            $table->index('coding_account_id', 'idx_coding_account');
            $table->foreign('coding_account_id')
                ->references('id')
                ->on('coding_accounts')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropForeign(['coding_account_id']);
            $table->dropIndex('idx_coding_account');
            $table->dropColumn('coding_account_id');
            $table->dropColumn('coding_status_override');
        });
    }
};
