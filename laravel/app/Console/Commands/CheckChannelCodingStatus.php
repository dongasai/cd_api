<?php

namespace App\Console\Commands;

use App\Services\CodingStatus\ChannelCodingStatusService;
use Illuminate\Console\Command;

/**
 * 检查渠道Coding状态定时任务
 *
 * 每分钟执行一次，检查所有绑定Coding账户的渠道状态
 */
class CheckChannelCodingStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coding:check-channels
                            {--channel= : 指定渠道ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检查渠道Coding状态并触发调控';

    protected ChannelCodingStatusService $codingStatusService;

    public function __construct(ChannelCodingStatusService $codingStatusService)
    {
        parent::__construct();
        $this->codingStatusService = $codingStatusService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // 如果指定了渠道ID
        if ($this->option('channel')) {
            $channel = \App\Models\Channel::find($this->option('channel'));
            if (!$channel) {
                $this->error('渠道不存在');
                return self::FAILURE;
            }

            $this->info("检查渠道: {$channel->name} (ID: {$channel->id})");
            $result = $this->codingStatusService->checkAndUpdateChannel($channel);

            $this->displayResult($channel->id, $result);

            return self::SUCCESS;
        }

        // 批量检查所有渠道
        $this->info('开始检查所有绑定Coding账户的渠道...');

        $results = $this->codingStatusService->batchCheckAndUpdate();

        $this->info("共检查 {$results['total']} 个渠道");
        $this->newLine();

        $updatedCount = 0;
        foreach ($results['results'] as $channelId => $result) {
            if ($result['updated'] ?? false) {
                $updatedCount++;
            }
            $this->displayResult($channelId, $result);
        }

        $this->newLine();
        $this->info("检查完成: {$updatedCount} 个渠道状态已更新");

        return self::SUCCESS;
    }

    /**
     * 显示检查结果
     */
    protected function displayResult(int $channelId, array $result): void
    {
        if ($result['updated'] ?? false) {
            $action = $result['action'] ?? 'unknown';
            $fromStatus = $result['from_status'] ?? 'unknown';
            $toStatus = $result['to_status'] ?? 'unknown';

            if ($action === 'disabled') {
                $this->warn("[渠道 {$channelId}] 已禁用 ({$fromStatus} → {$toStatus})");
            } elseif ($action === 'enabled') {
                $this->info("[渠道 {$channelId}] 已启用 ({$fromStatus} → {$toStatus})");
            }
        } elseif (isset($result['error'])) {
            $this->error("[渠道 {$channelId}] 错误: {$result['error']}");
        } elseif (isset($result['message'])) {
            $this->comment("[渠道 {$channelId}] {$result['message']}");
        }
    }
}
