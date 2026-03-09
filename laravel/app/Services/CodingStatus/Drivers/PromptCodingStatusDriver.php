<?php

namespace App\Services\CodingStatus\Drivers;

use App\Models\CodingAccount;

/**
 * Prompt Coding Status 驱动
 *
 * 按Prompt次数计费模式
 */
class PromptCodingStatusDriver extends AbstractCodingStatusDriver
{
    /**
     * 获取驱动名称
     */
    public function getName(): string
    {
        return 'Prompt次数计费';
    }

    /**
     * 获取驱动描述
     */
    public function getDescription(): string
    {
        return '按Prompt次数计费模式 - 适用于智谱GLM、MiniMax等平台';
    }

    /**
     * 获取支持的计费维度
     */
    public function getSupportedMetrics(): array
    {
        return [
            'prompts' => 'Prompt次数',
            'prompts_per_5h' => '5小时周期Prompt次数',
            'prompts_per_day' => '日周期Prompt次数',
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
        $cycle = $this->getCycle();

        $prompts = $context['prompts'] ?? 1;
        $model = $context['model'] ?? '';

        // 获取模型消耗倍数
        $multiplier = $this->getModelMultiplier($model);
        $adjustedPrompts = (int) ceil($prompts * $multiplier);

        $sufficient = true;
        $insufficientMetrics = [];

        // 根据周期类型检查不同的配额维度
        if ($cycle === '5h') {
            if (isset($limits['prompts_per_5h']) && $limits['prompts_per_5h'] > 0) {
                $used = $usage['prompts_per_5h'] ?? 0;
                if ($used + $adjustedPrompts > $limits['prompts_per_5h']) {
                    $sufficient = false;
                    $insufficientMetrics[] = 'prompts_per_5h';
                }
            }
        } elseif ($cycle === 'daily') {
            if (isset($limits['prompts_per_day']) && $limits['prompts_per_day'] > 0) {
                $used = $usage['prompts_per_day'] ?? 0;
                if ($used + $adjustedPrompts > $limits['prompts_per_day']) {
                    $sufficient = false;
                    $insufficientMetrics[] = 'prompts_per_day';
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

        return [
            'sufficient' => $sufficient,
            'insufficient_metrics' => $insufficientMetrics,
            'requested' => [
                'prompts' => $prompts,
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
        $model = $usage['model'] ?? '';
        $channelId = $usage['channel_id'] ?? null;
        $requestId = $usage['request_id'] ?? null;
        $cycle = $this->getCycle();

        // 获取模型消耗倍数
        $multiplier = $this->getModelMultiplier($model);
        $adjustedPrompts = (int) ceil($prompts * $multiplier);

        // 更新Redis中的使用量
        if ($cycle === '5h') {
            $this->incrementUsage('prompts_per_5h', $adjustedPrompts);
        } elseif ($cycle === 'daily') {
            $this->incrementUsage('prompts_per_day', $adjustedPrompts);
        } else {
            $this->incrementUsage('prompts', $adjustedPrompts);
        }

        // 记录到数据库
        $this->logUsage([
            'channel_id' => $channelId,
            'request_id' => $requestId,
            'prompts' => $adjustedPrompts,
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
        // Prompt次数计费模式通常不需要外部同步
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
        $cycle = $this->getCycle();

        $metrics = [];

        // 根据周期类型显示不同的配额维度
        if ($cycle === '5h') {
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
        } elseif ($cycle === 'daily') {
            if (isset($limits['prompts_per_day'])) {
                $used = $usage['prompts_per_day'] ?? 0;
                $limit = $limits['prompts_per_day'];
                $metrics['prompts_per_day'] = [
                    'label' => '日周期Prompt次数',
                    'used' => $used,
                    'limit' => $limit,
                    'remaining' => max(0, $limit - $used),
                    'rate' => $this->calculateUsageRate($used, $limit),
                ];
            }
        } else {
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
            'prompts' => $this->getCurrentUsage('prompts'),
            'prompts_per_5h' => $this->getCurrentUsage('prompts_per_5h'),
            'prompts_per_day' => $this->getCurrentUsage('prompts_per_day'),
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
            ],
            'thresholds' => [
                'warning' => 0.75,
                'disable' => 0.90,
            ],
            'cycle' => '5h',
            'period_offset' => 0,
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
                'help' => '月度或周度周期的Prompt限制',
            ],
            [
                'name' => 'limits.prompts_per_5h',
                'label' => '5小时周期Prompt限制',
                'type' => 'number',
                'min' => 0,
                'default' => 80,
                'help' => '智谱GLM等平台使用',
            ],
            [
                'name' => 'limits.prompts_per_day',
                'label' => '日周期Prompt限制',
                'type' => 'number',
                'min' => 0,
                'default' => 500,
                'help' => '每日Prompt限制',
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
                'name' => 'period_offset',
                'label' => '周期偏移量 (秒)',
                'type' => 'number',
                'min' => 0,
                'max' => 17999,
                'default' => 0,
                'help' => '5小时周期的起始偏移量',
            ],
            [
                'name' => 'reset_day',
                'label' => '重置日期',
                'type' => 'number',
                'min' => 1,
                'max' => 31,
                'default' => 1,
                'help' => '每月重置的日期 (仅月度周期有效)',
            ],
        ];
    }
}
