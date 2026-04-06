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
        Schema::table('coding_accounts', function (Blueprint $table) {
            $table->unsignedInteger('pause_duration_minutes')->nullable()->after('disabled_at')->comment('暂停时长（分钟）');
            $table->string('pause_reason')->nullable()->after('pause_duration_minutes')->comment('暂停原因');
            $table->unsignedBigInteger('pause_rule_id')->nullable()->after('pause_reason')->comment('触发暂停的规则ID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coding_accounts', function (Blueprint $table) {
            $table->dropColumn(['pause_duration_minutes', 'pause_reason', 'pause_rule_id']);
        });
    }
};
