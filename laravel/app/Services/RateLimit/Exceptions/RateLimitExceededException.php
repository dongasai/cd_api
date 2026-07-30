<?php

namespace App\Services\RateLimit\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 限流超出异常
 *
 * 当请求触发限流规则时抛出，HTTP 状态码 429
 */
class RateLimitExceededException extends HttpException
{
    /**
     * @param  string  $message  错误消息
     * @param  int|null  $retryAfter  重试等待时间（秒）
     * @param  \Throwable|null  $previous  前一个异常
     * @param  int  $code  错误码
     */
    public function __construct(
        string $message = 'Rate limit exceeded. Please try again later.',
        ?int $retryAfter = null,
        ?\Throwable $previous = null,
        int $code = 0,
    ) {
        $headers = [];
        if ($retryAfter !== null && $retryAfter > 0) {
            $headers['Retry-After'] = $retryAfter;
        }

        parent::__construct(429, $message, $previous, $headers, $code);
    }

    /**
     * 获取重试等待时间
     */
    public function getRetryAfter(): ?int
    {
        return $this->getHeaders()['Retry-After'] ?? null;
    }

    /**
     * 创建标准格式的响应数组
     */
    public function toArray(): array
    {
        $response = [
            'error' => [
                'message' => $this->getMessage(),
                'type' => 'rate_limit_exceeded',
                'code' => 'rate_limit_exceeded',
            ],
        ];

        if ($this->getRetryAfter() !== null) {
            $response['error']['retry_after'] = $this->getRetryAfter();
        }

        return $response;
    }
}
