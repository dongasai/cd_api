<?php

namespace App\Services\Router;

use Exception;

/**
 * 重试处理器
 */
class RetryHandler
{
    protected int $maxRetries;

    protected array $retryableStatusCodes;

    protected bool $enableFailover;

    public function __construct()
    {
        $this->maxRetries = config('router.max_retry', 3);
        $this->retryableStatusCodes = config('router.retryable_status_codes', [429, 500, 502, 503, 504]);
        $this->enableFailover = config('router.enable_failover', true);
    }

    /**
     * 判断是否应该重试
     */
    public function shouldRetry(Exception $e, int $attempt): bool
    {
        if (! $this->enableFailover) {
            return false;
        }

        if ($attempt >= $this->maxRetries) {
            return false;
        }

        $statusCode = $this->getStatusCode($e);

        return in_array($statusCode, $this->retryableStatusCodes, true) || $statusCode >= 500;
    }

    /**
     * 执行重试延迟
     */
    public function wait(int $attempt): void
    {
        if ($attempt <= $this->maxRetries) {
            $delay = min(100000 * pow(2, $attempt - 1), 1000000); // 最大延迟 1 秒
            usleep($delay);
        }
    }

    /**
     * 获取状态码
     */
    protected function getStatusCode(Exception $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        if (method_exists($e, 'getCode')) {
            return $e->getCode() ?: 500;
        }

        return 500;
    }

    /**
     * 获取最大重试次数
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }
}
