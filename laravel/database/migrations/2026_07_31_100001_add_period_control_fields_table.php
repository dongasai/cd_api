<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coding_accounts', function (Blueprint $table) {
            $table->boolean('period_control_enabled')->default(false)
                ->after('pause_rule_id')
                ->comment('是否启用可用时段控制');
            $table->string('period_disabled_reason')->nullable()
                ->after('period_control_enabled')
                ->comment('时段外禁用原因标记，outside_period 表示因时段控制禁用');
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->timestamp('period_disabled_at')->nullable()
                ->after('rpm_limit')
                ->comment('因可用时段控制禁用的时间，null 表示非时段控制禁用');
        });
    }

    public function down(): void
    {
        Schema::table('coding_accounts', function (Blueprint $table) {
            $table->dropColumn(['period_control_enabled', 'period_disabled_reason']);
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('period_disabled_at');
        });
    }
};
