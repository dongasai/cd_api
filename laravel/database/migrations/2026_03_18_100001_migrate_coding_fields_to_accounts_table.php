<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 将 channels 表的 Coding 配置字段迁移到 coding_accounts 表
 *
 * 变更说明：
 * - coding_status_override → coding_accounts.status_override
 * - coding_last_check_at → coding_accounts.last_check_at
 *
 * 理由：
 * - 这些配置应该属于 Coding 账户级别，而非渠道级别
 * - 检查粒度应该是账户级别，一次检查控制所有关联渠道
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. 在 coding_accounts 表新增字段
        Schema::table('coding_accounts', function (Blueprint $table) {
            $table->json('status_override')
                ->nullable()
                ->after('config')
                ->comment('状态覆盖配置：auto_disable, auto_enable, disable_threshold等');

            $table->timestamp('last_check_at')
                ->nullable()
                ->after('disabled_at')
                ->comment('最后检查时间');
        });

        // 2. 迁移数据：从 channels 到 coding_accounts
        // 同一 coding_account_id 可能被多个 channel 使用，取第一条的配置
        $channelsWithCoding = DB::table('channels')
            ->whereNotNull('coding_account_id')
            ->whereNotNull('coding_status_override')
            ->orderBy('id')
            ->get(['coding_account_id', 'coding_status_override', 'coding_last_check_at']);

        $accountUpdates = [];
        foreach ($channelsWithCoding as $channel) {
            $accountId = $channel->coding_account_id;

            // 如果该账户还没有配置，则使用这条渠道的配置
            if (! isset($accountUpdates[$accountId])) {
                $accountUpdates[$accountId] = [
                    'status_override' => $channel->coding_status_override,
                    'last_check_at' => $channel->coding_last_check_at,
                ];
            }
        }

        // 批量更新 coding_accounts
        foreach ($accountUpdates as $accountId => $data) {
            DB::table('coding_accounts')
                ->where('id', $accountId)
                ->update([
                    'status_override' => $data['status_override'],
                    'last_check_at' => $data['last_check_at'],
                    'updated_at' => now(),
                ]);
        }

        // 3. 从 channels 表移除字段
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('coding_status_override');
            $table->dropColumn('coding_last_check_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. 在 channels 表恢复字段
        Schema::table('channels', function (Blueprint $table) {
            $table->json('coding_status_override')
                ->nullable()
                ->after('coding_account_id')
                ->comment('渠道级别的Coding状态覆盖配置');

            $table->timestamp('coding_last_check_at')
                ->nullable()
                ->after('coding_status_override');
        });

        // 2. 迁移数据：从 coding_accounts 回到 channels
        $accounts = DB::table('coding_accounts')
            ->whereNotNull('status_override')
            ->get(['id', 'status_override', 'last_check_at']);

        foreach ($accounts as $account) {
            DB::table('channels')
                ->where('coding_account_id', $account->id)
                ->update([
                    'coding_status_override' => $account->status_override,
                    'coding_last_check_at' => $account->last_check_at,
                    'updated_at' => now(),
                ]);
        }

        // 3. 从 coding_accounts 表移除字段
        Schema::table('coding_accounts', function (Blueprint $table) {
            $table->dropColumn('status_override');
            $table->dropColumn('last_check_at');
        });
    }
};
