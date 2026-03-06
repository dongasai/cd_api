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
        return 'PromptCodingStatus';
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
            'status' =>