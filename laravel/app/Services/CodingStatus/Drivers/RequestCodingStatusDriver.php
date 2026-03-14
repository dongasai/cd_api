<?php

namespace App\Services\CodingStatus\Drivers;

use App\Models\CodingAccount;
use App\Models\CodingUsageLog;

/**
 * Request Coding Status 驱动
 *
 * 按请求次数计费模式
 */
class RequestCodingStatusDriver extends AbstractCodingStatusDriver
{
    /**
     * 获取驱动名称
     */
    public function getName(): string
    {
        return '请求次数计费';
    }

    /**
     * 获取驱动描述
     */
    public function getDescription(): string
    {
        return '按请求次数计费模式 - 适用于阿里云百炼、火山方舟等平台';
    }

    /**
     * 获取支持的计费维度
     */
    public function getSupportedMetrics(): array
    {
        return [
            'requests' => '请求次数',
            'requests_per_5h' => '5小时周期请求次数',
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

        $requests = $context['requests'] ?? 1;
        $model = $context['model'] ?? '';

        // 获取模型消耗倍数
        $multiplier = $this->getModelMultiplier($model);
        $adjustedRequests = (int) ceil($requests * $multiplier);

        $sufficient = true;
        $insufficientMetrics = [];

        // 根据周期类型检查不同的配额维度
        if ($cycle === '5h') {
            if (isset($limits['requests_per_5h']) && $limits['requests_per_5h'] > 0) {
                $used = $usage['requests_per_5h'] ?? 0;
                if ($used + $adjustedRequests > $limits['requests_per_5h']) {
                    $sufficient = false;
                    $insufficientMetrics[] = 'requests_per_5h';
                }
            }
        } else {
            if (isset($limits['requests']) && $limits['requests'] > 0) {
                $used = $usage['requests'] ?? 0;
                if ($used + $adjustedRequests > $limits['requests']) {
                    $sufficient = false;
                    $insufficientMetrics[] = 'requests';
                }
            }
        }

        return [
            'sufficient' => $sufficient,
            'insufficient_metrics' => $insufficientMetrics,
            'requested' => [
                'requests' => $requests,
            ],
            'adjusted' => [
                'requests' => $adjustedRequests,
            ],
            'multiplier' => $multiplier,
        ];
    }

    /**
     * 消耗配额
     */
    public function consume(array $usage): void
    {
        $requests = $usage['requests'] ?? 1;
        $model = $usage['model'] ?? '';
        $channelId = $usage['channel_id'] ?? null;
        $requestId = $usage['request_id'] ?? null;
        $cycle = $this->getCycle();

        // 获取模型消耗倍数
        $multiplier = $this->getModelMultiplier($model);
        $adjustedRequests = (int) ceil($requests * $multiplier);

        // 更新数据库中的使用量
        if ($cycle === '5h') {
            $this->incrementUsage('requests_per_5h', $adjustedRequests);
        } else {
            $this->incrementUsage('requests', $adjustedRequests);
        }

        // 记录到数据库
        $this->logUsage([
            'channel_id' => $channelId,
            'request_id' => $requestId,
            'requests' => $adjustedRequests,
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
        // 请求次数计费模式通常不需要外部同步
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
            if (isset($limits['requests_per_5h'])) {
                $used = $usage['requests_per_5h'] ?? 0;
                $limit = $limits['requests_per_5h'];
                $metrics['requests_per_5h'] = [
                    'label' => '5小时周期请求次数',
                    'used' => $used,
                    'limit' => $limit,
                    'remaining' => max(0, $limit - $used),
                    'rate' => $this->calculateUsageRate($used, $limit),
                ];
            }
        } else {
            if (isset($limits['requests'])) {
                $used = $usage['requests'] ?? 0;
                $limit = $limits['requests'];
                $metrics['requests'] = [
                    'label' => '请求次数',
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
            'requests' => $this->getCurrentUsage('requests'),
            'requests_per_5h' => $this->getCurrentUsage('requests_per_5h'),
        ];
    }

    /**
     * 获取默认配额配置
     */
    public function getDefaultQuotaConfig(): array
    {
        return [
            'limits' => [
                'requests_per_5h' => 1200,
            ],
            'thresholds' => [
                'warning' => 0.80,
                'critical' => 0.90,
                'disable' => 0.95,
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
                'name' => 'limits.requests',
                'label' => '请求次数限制 (月度/周度)',
                'type' => 'number',
                'min' => 0,
                'default' => 10000,
                'help' => '月度或周度周期的请求限制',
            ],
            [
                'name' => 'limits.requests_per_5h',
                'label' => '5小时周期请求限制',
                'type' => 'number',
                'min' => 0,
                'default' => 1200,
                'help' => '阿里云百炼、火山方舟等平台使用',
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
