<?php

namespace App\Console\Commands;

use App\Models\CodingAccount;
use App\Services\CodingStatus\CodingStatusDriverManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 同步Coding配额定时任务
 *
 * 每5分钟执行一次，同步所有Coding账户的配额状态
 */
class SyncCodingQuota extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coding:sync-quota
                            {--account= : 指定账户ID}
                            {--platform= : 指定平台类型}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步Coding账户配额状态';

    protected CodingStatusDriverManager $driverManager;

    public function __construct(CodingStatusDriverManager $driverManager)
    {
        parent::__construct();
        $this->driverManager = $driverManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = CodingAccount::query();

        // 如果指定了账户ID
        if ($this->option('account')) {
            $query->where('id', $this->option('account'));
        }

        // 如果指定了平台类型
        if ($this->option('platform')) {
            $query->where('platform', $this->option('platform'));
        }

        // 只同步活跃的账户
        $query->whereIn('status', [
            CodingAccount::STATUS_ACTIVE,
            CodingAccount::STATUS_WARNING,
            CodingAccount::STATUS_CRITICAL,
        ]);

        $accounts = $query->get();

        $this->info("开始同步 {$accounts->count()} 个Coding账户的配额...");

        $successCount = 0;
        $failCount = 0;

        foreach ($accounts as $account) {
            try {
                $this->info("正在同步账户: {$account->name} (ID: {$account->id})");

                $driver = $this->driverManager->driverForAccount($account);
                $driver->sync();

                $successCount++;
                $this->info("✓ 同步成功: {$account->name}");
            } catch (\Exception $e) {
                $failCount++;
                $this->error("✗ 同步失败: {$account->name} - {$e->getMessage()}");

                Log::error('Coding账户配额同步失败', [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'error' => $e->getMessage(),
                ]);

                // 增加错误计数
                $account->increment('sync_error_count');
                $account->update(['sync_error' => $e->getMessage()]);

                // 如果连续失败超过阈值，标记为错误状态
                if ($account->sync_error_count >= 5) {
                    $account->update(['status' => CodingAccount::STATUS_ERROR]);
                    $this->warn("  账户已标记为错误状态 (连续失败 {$account->sync_error_count} 次)");
                }
            }
        }

        $this->newLine();
        $this->info("同步完成: 成功 {$successCount} 个, 失败 {$failCount} 个");

        return self::SUCCESS;
    }
}
