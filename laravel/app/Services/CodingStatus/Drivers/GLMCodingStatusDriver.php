<?php

namespace App\Services\CodingStatus\Drivers;

use App\Models\CodingAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GLM Coding Status 驱动
 *
 * 智谱GLM官方API获取状态
 */
class GLMCodingStatusDriver extends AbstractCodingStatusDriver
{
    /**
     * API基础URL
     */
    protected string $apiBaseUrl = 'https://open.bigmodel.cn/api/paas/v4';

    /**
     * 获取驱动名称
     */
    public function getName(): string
    {
        return '智谱GLM官方API';
    }

    /**
     * 获取驱动描述
     */
    public function getDescription(): string
    {
        return '智谱GLM官方API获取状态 - 通过官方API获取实时配额状态';
    }

    /**
     * 获取支持的计费维度
     */
    public function getSupportedMetrics(): array
    {
        return [
            'prompts' => 'Prompt次数',
            'tokens' => 'Token消耗',
            'balance' => '账户余额',
        ];
    }

    /**
     * 获取当前配额状态
     */
    public function getStatus(): array
    {
        $quotaInfo = $this->getQuotaInfo();
        $metrics = $quotaInfo['metrics'] ?? [];

        $maxRate = 0.0;
        foreach ($metrics as $metric) {
            if (isset($metric['rate'])) {
                $maxRate = max($maxRate, $metric['rate']);
            }
        }

        // 根据最大使用率确定状态
        $status = $this->getStatusByUsageRate($maxRate);

        // 检查账户是否过期
        if ($this->account->isExpired()) {
            $status = CodingAccount::STATUS_EXPIRED;
        }

        // 检查是否有API错误
        $cached = $this->account->quota_cached ?? [];
        if (isset($cached['api_error']) && $cached['api_error']) {
            $status = CodingAccount::STATUS_ERROR;
        }

        return [
            'status' => $status,
            'usage_rate' => $maxRate,
            'metrics' => $metrics,
            'period' => $this->getPeriodInfo(),
        ];
    }

    /**
     * 检查配额是否充足
     */
    public function checkQuota(array $context): array
    {
        $limits = $this->getLimits();
        $usage = $this->getUsage();
        $cycle = $this->getCycle();

        $prompts = $context['prompts'] ?? 1;
        $tokensInput = $context['tokens_input'] ?? 0;
        $tokensOutput = $context['tokens_output'] ?? 0;
        $model = $context['model'] ?? '';

        // 获取模型消耗倍数
        $multiplier = $this->getModelMultiplier($model);
        $adjustedPrompts = (int) ceil($prompts * $multiplier);

        $sufficient = true;
        $insufficientMetrics = [];

        // 检查Prompt配额
        if ($cycle === '5h') {
            if (isset($limits['prompts_per_5h']) && $limits['prompts_per_5h'] > 0) {
                $used = $usage['prompts_per_5h'] ?? 0;
                if ($used + $adjustedPrompts > $limits['prompts_per_5h']) {
                    $sufficient = false;
                    $insufficientMetrics[] = 'prompts_per_5h';
                }
            }
        } else {
            if (isset($limits['prompts']) && $limits['prompts'] > 0) {
                $used = $usage['prompts'] ?? 0;
                if ($used + $adjustedPrompts > $limits['prompts']) {
                    $sufficient = false;
                    $insufficientMetrics[] = 'prompts';
                }
            }
        }

        // 检查Token配额
        if (isset($limits['tokens']) && $limits['tokens'] > 0) {
            $used = $usage['tokens'] ?? 0;
            $totalTokens = $tokensInput + $tokensOutput;
            $adjustedTokens = (int) ($totalTokens * $multiplier);
            if ($used + $adjustedTokens > $limits['tokens']) {
                $sufficient = false;
                $insufficientMetrics[] = 'tokens';
            }
        }

        return [
            'sufficient' => $sufficient,
            'insufficient_metrics' => $insufficientMetrics,
            'requested' => [
                'prompts' => $prompts,
                'tokens_input' => $tokensInput,
                'tokens_output' => $tokensOutput,
            ],
            'adjusted' => [
                'prompts' => $adjustedPrompts,
            ],
            'multiplier' => $multiplier,
        ];
    }

    /**
     * 消耗配额
     */
    public function consume(array $usage): void
    {
        $prompts = $usage['prompts'] ?? 1;
        $tokensInput = $usage['tokens_input'] ?? 0;
        $tokensOutput = $usage['tokens_output'] ?? 0;
        $model = $usage['model'] ?? '';
        $channelId = $usage['channel_id'] ?? null;
        $requestId = $usage['request_id'] ?? null;
        $cycle = $this->getCycle();

        // 获取模型消耗倍数
        $multiplier = $this->getModelMultiplier($model);
        $adjustedPrompts = (int) ceil($prompts * $multiplier);
        $totalTokens = $tokensInput + $tokensOutput;
        $adjustedTokens = (int) ($totalTokens * $multiplier);

        // 更新数据库中的使用量
        if ($cycle === '5h') {
            $this->incrementUsage('prompts_per_5h', $adjustedPrompts);
        } else {
            $this->incrementUsage('prompts', $adjustedPrompts);
        }

        if ($adjustedTokens > 0) {
            $this->incrementUsage('tokens', $adjustedTokens);
        }

        // 记录到数据库
        $this->logUsage([
            'channel_id' => $channelId,
            'request_id' => $requestId,
            'prompts' => $adjustedPrompts,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'model' => $model,
            'model_multiplier' => $multiplier,
            'status' => $usage['status'] ?? CodingUsageLog::STATUS_SUCCESS,
            'metadata' => $usage['metadata'] ?? null,
        ]);
    }

    /**
     * 同步配额信息
     *
     * 从智谱GLM官方API获取最新配额状态
     */
    public function sync(): void
    {
        try {
            $credentials = $this->account->getCredentials();
            $apiKey = $credentials['api_key'] ?? null;

            if (empty($apiKey)) {
                throw new \Exception('API Key 未配置');
            }

            // 调用智谱GLM API获取配额信息
            // 注意: 这里使用模拟数据，实际实现需要根据智谱GLM的真实API调整
            $response = $this->fetchQuotaFromAPI($apiKey);

            $quotaCached = [
                'synced_at' => now()->toDateTimeString(),
                'usage' => $response['usage'] ?? [],
                'limits' => $response['limits'] ?? [],
                'balance' => $response['balance'] ?? null,
                'api_error' => false,
            ];

            $this->account->update([
                'quota_cached' => $quotaCached,
                'last_sync_at' => now(),
                'sync_error' => null,
                'sync_error_count' => 0,
            ]);
        } catch (\Exception $e) {
            Log::error('GLM Coding Status 同步失败', [
                'account_id' => $this->account->id,
                'error' => $e->getMessage(),
            ]);

            $syncErrorCount = $this->account->sync_error_count + 1;

            $this->account->update([
                'sync_error' => $e->getMessage(),
                'sync_error_count' => $syncErrorCount,
            ]);

            // 如果连续同步失败超过阈值，标记为错误状态
            if ($syncErrorCount >= 5) {
                $this->account->update(['status' => CodingAccount::STATUS_ERROR]);
            }
        }
    }

    /**
     * 从API获取配额信息
     */
    protected function fetchQuotaFromAPI(string $apiKey): array
    {
        // 这里应该调用智谱GLM的真实API
        // 目前返回模拟数据，实际部署时需要替换为真实API调用

        // 示例API调用 (需要根据智谱GLM的实际API文档调整):
        // $response = Http::withHeaders([
        //     'Authorization' => 'Bearer ' . $apiKey,
        // ])->get($this->apiBaseUrl . '/user/quota');

        // if ($response->failed()) {
        //     throw new \Exception('API请求失败: ' . $response->body());
        // }

        // return $response->json();

        // 模拟数据
        $limits = $this->getLimits();
        $usage = $this->getUsage();

        return [
            'usage' => $usage,
            'limits' => $limits,
            'balance' => null, // 如果有余额信息
        ];
    }

    /**
     * 获取配额详细信息
     */
    public function getQuotaInfo(): array
    {
        $limits = $this->getLimits();
        $usage = $this->getUsage();
        $periodInfo = $this->getPeriodInfo();
        $cached = $this->account->quota_cached ?? [];

        $metrics = [];

        // Prompt配额
        if (isset($limits['prompts'])) {
            $used = $usage['prompts'] ?? 0;
            $limit = $limits['prompts'];
            $metrics['prompts'] = [
                'label' => 'Prompt次数',
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
                'rate' => $this->calculateUsageRate($used, $limit),
            ];
        }

        if (isset($limits['prompts_per_5h'])) {
            $used = $usage['prompts_per_5h'] ?? 0;
            $limit = $limits['prompts_per_5h'];
            $metrics['prompts_per_5h'] = [
                'label' => '5小时周期Prompt次数',
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
                'rate' => $this->calculateUsageRate($used, $limit),
            ];
        }

        // Token配额
        if (isset($limits['tokens'])) {
            $used = $usage['tokens'] ?? 0;
            $limit = $limits['tokens'];
            $metrics['tokens'] = [
                'label' => 'Token消耗',
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
                'rate' => $this->calculateUsageRate($used, $limit),
            ];
        }

        // 余额信息
        if (isset($cached['balance'])) {
            $metrics['balance'] = [
                'label' => '账户余额',
                'value' => $cached['balance'],
            ];
        }

        return [
            'metrics' => $metrics,
            'period' => $periodInfo,
            'status' => $this->getStatus(),
            'last_sync' => $cached['synced_at'] ?? null,
        ];
    }

    /**
     * 获取使用量
     */
    protected function getUsage(): array
    {
        // 优先从缓存获取
        $cached = $this->account->quota_cached ?? [];
        if (! empty($cached['usage'])) {
            return $cached['usage'];
        }

        // 从数据库获取
        return [
            'prompts' => $this->getCurrentUsage('prompts'),
            'prompts_per_5h' => $this->getCurrentUsage('prompts_per_5h'),
            'tokens' => $this->getCurrentUsage('tokens'),
        ];
    }

    /**
     * 验证账户凭证
     */
    public function validateCredentials(): array
    {
        $credentials = $this->account->getCredentials();
        $apiKey = $credentials['api_key'] ?? null;

        if (empty($apiKey)) {
            return [
                'valid' => false,
                'message' => 'API Key 未配置',
            ];
        }

        // 验证API Key格式 (智谱GLM的API Key通常以特定前缀开头)
        if (! str_starts_with($apiKey, 'sk-') && ! str_starts_with($apiKey, 'eyJ')) {
            return [
                'valid' => false,
                'message' => 'API Key 格式不正确',
            ];
        }

        return [
            'valid' => true,
            'message' => '凭证格式正确',
        ];
    }

    /**
     * 获取默认配额配置
     */
    public function getDefaultQuotaConfig(): array
    {
        return [
            'limits' => [
                'prompts_per_5h' => 80,
                'tokens' => 1000000,
            ],
            'thresholds' => [
                'warning' => 0.75,
                'critical' => 0.85,
                'disable' => 0.90,
            ],
            'cycle' => '5h',
            'period_offset' => 0,
            'api_config' => [
                'sync_interval' => 300,
                'timeout' => 10000,
                'retry_attempts' => 3,
            ],
        ];
    }

    /**
     * 获取配置表单字段
     */
    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'limits.prompts',
                'label' => 'Prompt次数限制 (月度/周度)',
                'type' => 'number',
                'min' => 0,
                'default' => 1000,
            ],
            [
                'name' => 'limits.prompts_per_5h',
                'label' => '5小时周期Prompt限制',
                'type' => 'number',
                'min' => 0,
                'default' => 80,
            ],
            [
                'name' => 'limits.tokens',
                'label' => 'Token限制',
                'type' => 'number',
                'min' => 0,
                'default' => 1000000,
            ],
            [
                'name' => 'thresholds',
                'label' => '阈值配置',
                'type' => 'key_value',
                'help' => 'warning: 警告阈值, critical: 临界阈值, disable: 禁用阈值',
                'default' => [
                    'warning' => 0.75,
                    'critical' => 0.85,
                    'disable' => 0.90,
                ],
            ],
            [
                'name' => 'cycle',
                'label' => '重置周期',
                'type' => 'select',
                'options' => [
                    '5h' => '5小时',
                    'daily' => '每日',
                    'weekly' => '每周',
                    'monthly' => '每月',
                ],
                'default' => '5h',
            ],
            [
                'name' => 'api_config.sync_interval',
                'label' => '同步间隔 (秒)',
                'type' => 'number',
                'min' => 60,
                'max' => 3600,
                'default' => 300,
            ],
            [
                'name' => 'api_config.timeout',
                'label' => 'API超时 (毫秒)',
                'type' => 'number',
                'min' => 1000,
                'max' => 60000,
                'default' => 10000,
            ],
        ];
    }

    /**
     * 格式化配额数值显示
     *
     * GLM驱动显示：Prompt次数 + Token消耗
     */
    public function formatQuotaDisplay(): string
    {
        $quotaInfo = $this->getQuotaInfo();
        $metrics = $quotaInfo['metrics'] ?? [];

        if (empty($metrics)) {
            return '<span class="text-muted">暂无数据</span>';
        }

        $displayParts = [];

        // 优先显示Prompt配额
        if (isset($metrics['prompts'])) {
            $data = $metrics['prompts'];
            $used = (int) $data['used'];
            $limit = (int) $data['limit'];
            $percent = $limit > 0 ? round($used / $limit * 100, 1) : 0;

            $color = $this->getColorByPercent($percent);
            $displayParts[] = "<span class='text-{$color}'>Prompt: {$used}/{$limit}</span>";
        }

        // 显示Token消耗
        if (isset($metrics['tokens'])) {
            $data = $metrics['tokens'];
            $used = (int) $data['used'];
            $limit = (int) $data['limit'];
            $percent = $limit > 0 ? round($used / $limit * 100, 1) : 0;

            $color = $this->getColorByPercent($percent);
            $formattedUsed = $this->formatTokenNumber($used);
            $formattedLimit = $this->formatTokenNumber($limit);
            $displayParts[] = "<span class='text-{$color}'>Token: {$formattedUsed}/{$formattedLimit}</span>";
        }

        return implode('<br>', $displayParts);
    }

    /**
     * 根据百分比获取颜色
     */
    protected function getColorByPercent(float $percent): string
    {
        if ($percent >= 95) {
            return 'danger';
        }

        if ($percent >= 90) {
            return 'warning';
        }

        if ($percent >= 80) {
            return 'info';
        }

        return 'success';
    }

    /**
     * 格式化Token数值显示
     */
    protected function formatTokenNumber(int $number): string
    {
        if ($number >= 1000000) {
            return round($number / 1000000, 1).'M';
        }

        if ($number >= 1000) {
            return round($number / 1000, 1).'K';
        }

        return (string) $number;
    }
}
