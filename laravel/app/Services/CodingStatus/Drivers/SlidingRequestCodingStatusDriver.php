<?php

namespace App\Services\CodingStatus\Drivers;

use App\Models\CodingAccount;
use App\Models\CodingSlidingUsageLog;
use App\Models\ModelMultiplier;
use App\Services\CodingStatus\SlidingWindowRepository;

class SlidingRequestCodingStatusDriver implements CodingStatusDriver
{
    protected CodingAccount $account;

    protected SlidingWindowRepository $repository;

    public function __construct(SlidingWindowRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getName(): string
    {
        return '滑动窗口请求计费';
    }

    public function getDescription(): string
    {
        return '统计过去N小时/天内的请求次数';
    }

    public function getSupportedMetrics(): array
    {
        return [
            'requests' => '请求次数',
        ];
    }

    public function setAccount(CodingAccount $account): self
    {
        $this->account = $account;

        return $this;
    }

    protected function getWindowType(): string
    {
        $config = $this->account->getQuotaConfig();

        return $config['window_type'] ?? '5h';
    }

    protected function getLimits(): array
    {
        $config = $this->account->getQuotaConfig();

        return $config['limits'] ?? [];
    }

    protected function getThresholds(): array
    {
        $config = $this->account->getQuotaConfig();

        return $config['thresholds'] ?? [
            'warning' => 0.80,
            'critical' => 0.90,
            'disable' => 0.95,
        ];
    }

    protected function getModelMultiplier(string $model): float
    {
        return ModelMultiplier::findMultiplier($this->account->platform, $model);
    }

    protected function calculateUsageRate(int $used, int $limit): float
    {
        if ($limit <= 0) {
            return 0.0;
        }

        return min(1.0, $used / $limit);
    }

    protected function getStatusByUsageRate(float $rate): string
    {
        $thresholds = $this->getThresholds();

        if ($rate >= ($thresholds['disable'] ?? 0.95)) {
            return CodingAccount::STATUS_EXHAUSTED;
        }

        if ($rate >= ($thresholds['critical'] ?? 0.90)) {
            return CodingAccount::STATUS_CRITICAL;
        }

        if ($rate >= ($thresholds['warning'] ?? 0.80)) {
            return CodingAccount::STATUS_WARNING;
        }

        return CodingAccount::STATUS_ACTIVE;
    }

    public function getStatus(): array
    {
        $limits = $this->getLimits();
        $windowType = $this->getWindowType();
        $usedRequests = $this->repository->getRequestCountInWindow($this->account, $windowType);

        $rates = [];
        $maxRate = 0.0;

        if (isset($limits['requests']) && $limits['requests'] > 0) {
            $rate = $this->calculateUsageRate($usedRequests, $limits['requests']);
            $rates['requests'] = [
                'used' => $usedRequests,
                'limit' => $limits['requests'],
                'rate' => $rate,
            ];
            $maxRate = $rate;
        }

        $status = $this->getStatusByUsageRate($maxRate);

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

    public function checkQuota(array $context): array
    {
        $limits = $this->getLimits();
        $windowType = $this->getWindowType();
        $usedRequests = $this->repository->getRequestCountInWindow($this->account, $windowType);

        $requests = $context['requests'] ?? 1;
        $model = $context['model'] ?? '';

        $multiplier = $this->getModelMultiplier($model);
        $adjustedRequests = (int) ceil($requests * $multiplier);

        $sufficient = true;
        $insufficientMetrics = [];

        if (isset($limits['requests']) && $limits['requests'] > 0) {
            if ($usedRequests + $adjustedRequests > $limits['requests']) {
                $sufficient = false;
                $insufficientMetrics[] = 'requests';
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

    public function consume(array $usage): void
    {
        $requests = $usage['requests'] ?? 1;
        $model = $usage['model'] ?? '';

        $multiplier = $this->getModelMultiplier($model);
        $adjustedRequests = (int) ceil($requests * $multiplier);

        $this->repository->recordUsage($this->account, [
            'channel_id' => $usage['channel_id'] ?? null,
            'request_id' => $usage['request_id'] ?? null,
            'requests' => $adjustedRequests,
            'model' => $model,
            'model_multiplier' => $multiplier,
            'status' => $usage['status'] ?? CodingSlidingUsageLog::STATUS_SUCCESS,
            'metadata' => $usage['metadata'] ?? null,
        ]);
    }

    public function shouldDisable(): bool
    {
        $status = $this->getStatus();

        return in_array($status['status'], [
            CodingAccount::STATUS_EXHAUSTED,
            CodingAccount::STATUS_EXPIRED,
            CodingAccount::STATUS_SUSPENDED,
            CodingAccount::STATUS_ERROR,
        ], true);
    }

    public function shouldEnable(): bool
    {
        $status = $this->getStatus();

        if ($this->account->status === CodingAccount::STATUS_EXHAUSTED) {
            return in_array($status['status'], [
                CodingAccount::STATUS_ACTIVE,
                CodingAccount::STATUS_WARNING,
                CodingAccount::STATUS_CRITICAL,
            ], true);
        }

        if ($this->account->status === CodingAccount::STATUS_EXPIRED) {
            return ! $this->account->isExpired();
        }

        return false;
    }

    public function sync(): void
    {
        $windowType = $this->getWindowType();
        $usedRequests = $this->repository->getRequestCountInWindow($this->account, $windowType);

        $this->account->update([
            'quota_cached' => [
                'synced_at' => now()->toDateTimeString(),
                'used_requests' => $usedRequests,
                'limits' => $this->getLimits(),
                'window_type' => $windowType,
            ],
            'last_sync_at' => now(),
        ]);
    }

    public function getQuotaInfo(): array
    {
        $limits = $this->getLimits();
        $windowType = $this->getWindowType();
        $usedRequests = $this->repository->getRequestCountInWindow($this->account, $windowType);

        $metrics = [];

        if (isset($limits['requests'])) {
            $metrics['requests'] = [
                'label' => 'Request Count',
                'used' => $usedRequests,
                'limit' => $limits['requests'],
                'remaining' => max(0, $limits['requests'] - $usedRequests),
                'rate' => $this->calculateUsageRate($usedRequests, $limits['requests']),
            ];
        }

        return [
            'metrics' => $metrics,
            'period' => $this->getPeriodInfo(),
            'status' => $this->getStatus(),
        ];
    }

    public function getPeriodInfo(): array
    {
        $windowType = $this->getWindowType();

        return $this->repository->getPeriodInfo($windowType);
    }

    public function validateCredentials(): array
    {
        return [
            'valid' => true,
            'message' => '滑动窗口驱动不需要外部凭证',
        ];
    }

    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'limits.requests',
                'label' => '请求次数限制',
                'type' => 'number',
                'min' => 0,
                'default' => 1200,
            ],
            [
                'name' => 'window_type',
                'label' => '窗口类型',
                'type' => 'select',
                'options' => [
                    '5h' => '5小时',
                    '1d' => '1天',
                    '7d' => '7天',
                    '30d' => '30天',
                ],
                'default' => '5h',
            ],
            [
                'name' => 'thresholds',
                'label' => '阈值配置',
                'type' => 'key_value',
                'default' => [
                    'warning' => 0.80,
                    'critical' => 0.90,
                    'disable' => 0.95,
                ],
            ],
        ];
    }

    public function getDefaultQuotaConfig(): array
    {
        return [
            'limits' => [
                'requests' => 1200,
            ],
            'thresholds' => [
                'warning' => 0.80,
                'critical' => 0.90,
                'disable' => 0.95,
            ],
            'window_type' => '5h',
            'check_interval' => 300,
        ];
    }

    public function getCheckInterval(): int
    {
        $config = $this->account->getQuotaConfig();

        return $config['check_interval'] ?? 300;
    }
}
