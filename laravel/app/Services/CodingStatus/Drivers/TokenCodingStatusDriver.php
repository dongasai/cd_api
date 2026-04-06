<?php

namespace App\Services\CodingStatus\Drivers;

use App\Models\CodingAccount;

/**
 * Token Coding Status 驱动
 *
 * 按Token计费模式
 */
class TokenCodingStatusDriver extends AbstractCodingStatusDriver
{
    /**
     * 获取驱动名称
     */
    public function getName(): string
    {
        return 'Token计费';
    }

    /**
     * 获取驱动描述
     */
    public function getDescription(): string
    {
        return '按Token计费模式 - 适用于按输入/输出Token计费的平台';
    }

    /**
     * 获取支持的计费维度
     */
    public function getSupportedMetrics(): array
    {
        return [
            'tokens_input' => '输入Token',
            'tokens_output' => '输出Token',
            'tokens_total' => '总Token',
            'credits' => '积分',
        ];
    }

    /**
     * 获取当前配额状态
     */
    public function getStatus(): array
    {
        $limits = $this->getLimits();
        $usage = $this->getUsage();
        $rates = [];
        $maxRate = 0.0;

        // 计算各维度使用率
        foreach ($limits as $metric => $limit) {
            if ($limit > 0) {
                $used = $usage[$metric] ?? 0;
                $rate = $this->calculateUsageRate($used, $limit);
                $rates[$metric] = [
                    'used' => $used,
                    'limit' => $limit,
                    'rate' => $rate,
                ];
                $maxRate = max($maxRate, $rate);
            }
        }

        // 根据最大使用率确定状态
        $status = $this->getStatusByUsageRate($maxRate);

        // 检查账户是否过期
        if ($this->account->isExpired()) {
            $status = CodingAccount::STATUS_EXPIRED;
        }

        return [
            'status' => $status,
            'usage_rate' => $maxRate,
            'rates' => $rates,
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

        $tokensInput = $context['tokens_input'] ?? 0;
        $tokensOutput = $context['tokens_output'] ?? 0;
        $tokensTotal = $tokensInput + $tokensOutput;
        $model = $context['model'] ?? '';

        // 获取模型消耗倍数
        $multiplier = $this->getModelMultiplier($model);
        $adjustedTokensInput = (int) ($tokensInput * $multiplier);
        $adjustedTokensOutput = (int) ($tokensOutput * $multiplier);
        $adjustedTokensTotal = $adjustedTokensInput + $adjustedTokensOutput;

        $sufficient = true;
        $insufficientMetrics = [];

        // 检查各维度配额
        if (isset($limits['tokens_input']) && $limits['tokens_input'] > 0) {
            $used = $usage['tokens_input'] ?? 0;
            if ($used + $adjustedTokensInput > $limits['tokens_input']) {
                $sufficient = false;
                $insufficientMetrics[] = 'tokens_input';
            }
        }

        if (isset($limits['tokens_output']) && $limits['tokens_output'] > 0) {
            $used = $usage['tokens_output'] ?? 0;
            if ($used + $adjustedTokensOutput > $limits['tokens_output']) {
                $sufficient = false;
                $insufficientMetrics[] = 'tokens_output';
            }
        }

        if (isset($limits['tokens_total']) && $limits['tokens_total'] > 0) {
            $used = $usage['tokens_total'] ?? 0;
            if ($used + $adjustedTokensTotal > $limits['tokens_total']) {
                $sufficient = false;
                $insufficientMetrics[] = 'tokens_total';
            }
        }

        return [
            'sufficient' => $sufficient,
            'insufficient_metrics' => $insufficientMetrics,
            'requested' => [
                'tokens_input' => $tokensInput,
                'tokens_output' => $tokensOutput,
                'tokens_total' => $tokensTotal,
            ],
            'adjusted' => [
                'tokens_input' => $adjustedTokensInput,
                'tokens_output' => $adjustedTokensOutput,
                'tokens_total' => $adjustedTokensTotal,
            ],
            'multiplier' => $multiplier,
        ];
    }

    /**
     * 消耗配额
     */
    public function consume(array $usage): void
    {
        $tokensInput = $usage['tokens_input'] ?? 0;
        $tokensOutput = $usage['tokens_output'] ?? 0;
        $model = $usage['model'] ?? '';
        $channelId = $usage['channel_id'] ?? null;
        $requestId = $usage['request_id'] ?? null;

        // 获取模型消耗倍数
        $multiplier = $this->getModelMultiplier($model);
        $adjustedTokensInput = (int) ($tokensInput * $multiplier);
        $adjustedTokensOutput = (int) ($tokensOutput * $multiplier);
        $adjustedTokensTotal = $adjustedTokensInput + $adjustedTokensOutput;

        // 更新数据库中的使用量
        if ($adjustedTokensInput > 0) {
            $this->incrementUsage('tokens_input', $adjustedTokensInput);
        }
        if ($adjustedTokensOutput > 0) {
            $this->incrementUsage('tokens_output', $adjustedTokensOutput);
        }
        if ($adjustedTokensTotal > 0) {
            $this->incrementUsage('tokens_total', $adjustedTokensTotal);
        }

        // 记录到数据库
        $this->logUsage([
            'channel_id' => $channelId,
            'request_id' => $requestId,
            'tokens_input' => $adjustedTokensInput,
            'tokens_output' => $adjustedTokensOutput,
            'model' => $model,
            'model_multiplier' => $multiplier,
            'status' => $usage['status'] ?? CodingUsageLog::STATUS_SUCCESS,
            'metadata' => $usage['metadata'] ?? null,
        ]);
    }

    /**
     * 同步配额信息
     */
    public function sync(): void
    {
        // Token计费模式通常不需要外部同步
        // 可以在这里实现从外部API获取配额信息

        $quotaCached = [
            'synced_at' => now()->toDateTimeString(),
            'usage' => $this->getUsage(),
            'limits' => $this->getLimits(),
        ];

        $this->account->update([
            'quota_cached' => $quotaCached,
            'last_sync_at' => now(),
        ]);
    }

    /**
     * 获取配额详细信息
     */
    public function getQuotaInfo(): array
    {
        $limits = $this->getLimits();
        $usage = $this->getUsage();
        $periodInfo = $this->getPeriodInfo();

        $metrics = [];
        foreach ($this->getSupportedMetrics() as $metric => $label) {
            if (isset($limits[$metric])) {
                $used = $usage[$metric] ?? 0;
                $limit = $limits[$metric];
                $metrics[$metric] = [
                    'label' => $label,
                    'used' => $used,
                    'limit' => $limit,
                    'remaining' => max(0, $limit - $used),
                    'rate' => $this->calculateUsageRate($used, $limit),
                ];
            }
        }

        return [
            'metrics' => $metrics,
            'period' => $periodInfo,
            'status' => $this->getStatus(),
        ];
    }

    /**
     * 获取使用量
     */
    protected function getUsage(): array
    {
        return [
            'tokens_input' => $this->getCurrentUsage('tokens_input'),
            'tokens_output' => $this->getCurrentUsage('tokens_output'),
            'tokens_total' => $this->getCurrentUsage('tokens_total'),
            'credits' => $this->getCurrentUsage('credits'),
        ];
    }

    /**
     * 获取默认配额配置
     */
    public function getDefaultQuotaConfig(): array
    {
        return [
            'limits' => [
                'tokens_input' => 10000000,
                'tokens_output' => 5000000,
                'tokens_total' => 15000000,
            ],
            'thresholds' => [
                'warning' => 0.80,
                'critical' => 0.90,
                'disable' => 0.95,
            ],
            'cycle' => 'monthly',
            'reset_day' => 1,
        ];
    }

    /**
     * 获取配置表单字段
     */
    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'limits.tokens_input',
                'label' => '输入Token限制',
                'type' => 'number',
                'min' => 0,
                'default' => 10000000,
            ],
            [
                'name' => 'limits.tokens_output',
                'label' => '输出Token限制',
                'type' => 'number',
                'min' => 0,
                'default' => 5000000,
            ],
            [
                'name' => 'limits.tokens_total',
                'label' => '总Token限制',
                'type' => 'number',
                'min' => 0,
                'default' => 15000000,
            ],
            [
                'name' => 'thresholds',
                'label' => '阈值配置',
                'type' => 'key_value',
                'help' => 'warning: 警告阈值, critical: 临界阈值, disable: 禁用阈值',
                'default' => [
                    'warning' => 0.80,
                    'critical' => 0.90,
                    'disable' => 0.95,
                ],
            ],
            [
                'name' => 'cycle',
                'label' => '重置周期',
                'type' => 'select',
                'options' => [
                    'daily' => '每日',
                    'weekly' => '每周',
                    'monthly' => '每月',
                ],
                'default' => 'monthly',
            ],
            [
                'name' => 'reset_day',
                'label' => '重置日期',
                'type' => 'number',
                'min' => 1,
                'max' => 31,
                'default' => 1,
            ],
        ];
    }

    /**
     * 格式化配额数值显示
     *
     * Token驱动显示：输入/输出Token（用K/M单位）
     */
    public function formatQuotaDisplay(): string
    {
        $quotaInfo = $this->getQuotaInfo();
        $metrics = $quotaInfo['metrics'] ?? [];

        if (empty($metrics)) {
            return '<span class="text-muted">暂无数据</span>';
        }

        $displayParts = [];

        foreach ($metrics as $key => $data) {
            $used = (int) $data['used'];
            $limit = (int) $data['limit'];
            $percent = $limit > 0 ? round($used / $limit * 100, 1) : 0;

            // 根据使用率选择颜色
            $color = 'success';
            if ($percent >= 95) {
                $color = 'danger';
            } elseif ($percent >= 90) {
                $color = 'warning';
            } elseif ($percent >= 80) {
                $color = 'info';
            }

            // 格式化数值
            $formattedUsed = $this->formatTokenNumber($used);
            $formattedLimit = $this->formatTokenNumber($limit);

            $label = $data['label'] ?? 'Token';
            $displayParts[] = "<span class='text-{$color}'>{$label}: {$formattedUsed}/{$formattedLimit}</span>";
        }

        return implode('<br>', $displayParts);
    }

    /**
     * 格式化Token数值显示
     *
     * 将大数字转换为更易读的格式 (K, M)
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
