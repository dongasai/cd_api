<?php

namespace App\Services\Provider\Exceptions;

/**
 * 供应商异常类
 *
 * 用于处理 AI 服务供应商相关的错误
 */
class ProviderException extends \RuntimeException
{
    /**
     * 错误类型常量
     */
    public const TYPE_NETWORK_ERROR = 'network_error';

    public const TYPE_AUTH_ERROR = 'authentication_error';

    public const TYPE_RATE_LIMIT = 'rate_limit_exceeded';

    public const TYPE_INVALID_REQUEST = 'invalid_request';

    public const TYPE_MODEL_NOT_FOUND = 'model_not_found';

    public const TYPE_CONTEXT_LENGTH = 'context_length_exceeded';

    public const TYPE_CONTENT_FILTER = 'content_filter';

    public const TYPE_SERVER_ERROR = 'server_error';

    public const TYPE_TIMEOUT = 'timeout';

    public const TYPE_CIRCUIT_OPEN = 'circuit_breaker_open';

    public const TYPE_UNKNOWN = 'unknown_error';

    /**
     * 错误类型
     */
    protected string $errorType;

    /**
     * 原始错误数据
     */
    protected ?array $rawError = null;

    /**
     * 构造函数
     *
     * @param  string  $message  错误消息
     * @param  string  $errorType  错误类型
     * @param  int  $code  错误码
     * @param  \Throwable|null  $previous  前一个异常
     * @param  array|null  $rawError  原始错误数据
     */
    public function __construct(
        string $message,
        string $errorType = self::TYPE_UNKNOWN,
        int $code = 0,
        ?\Throwable $previous = null,
        ?array $rawError = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorType = $errorType;
        $this->rawError = $rawError;
    }

    /**
     * 获取错误类型
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * 获取原始错误数据
     */
    public function getRawError(): ?array
    {
        return $this->rawError;
    }

    /**
     * 创建网络错误异常
     */
    public static function networkError(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, self::TYPE_NETWORK_ERROR, 0, $previous);
    }

    /**
     * 创建认证错误异常
     */
    public static function authError(string $message, ?array $rawError = null): self
    {
        return new self($message, self::TYPE_AUTH_ERROR, 401, null, $rawError);
    }

    /**
     * 创建速率限制异常
     */
    public static function rateLimit(string $message, ?array $rawError = null): self
    {
        return new self($message, self::TYPE_RATE_LIMIT, 429, null, $rawError);
    }

    /**
     * 创建无效请求异常
     */
    public static function invalidRequest(string $message, ?array $rawError = null): self
    {
        return new self($message, self::TYPE_INVALID_REQUEST, 400, null, $rawError);
    }

    /**
     * 创建模型未找到异常
     */
    public static function modelNotFound(string $model, ?array $rawError = null): self
    {
        return new self(
            "Model '{$model}' not found",
            self::TYPE_MODEL_NOT_FOUND,
            404,
            null,
            $rawError
        );
    }

    /**
     * 创建上下文长度超限异常
     */
    public static function contextLengthExceeded(int $tokens, int $limit, ?array $rawError = null): self
    {
        return new self(
            "Context length exceeded: {$tokens} tokens, limit is {$limit}",
            self::TYPE_CONTEXT_LENGTH,
            400,
            null,
            $rawError
        );
    }

    /**
     * 创建内容过滤异常
     */
    public static function contentFilter(string $message, ?array $rawError = null): self
    {
        return new self($message, self::TYPE_CONTENT_FILTER, 400, null, $rawError);
    }

    /**
     * 创建服务器错误异常
     */
    public static function serverError(string $message, int $code = 500, ?array $rawError = null): self
    {
        return new self($message, self::TYPE_SERVER_ERROR, $code, null, $rawError);
    }

    /**
     * 创建超时异常
     */
    public static function timeout(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, self::TYPE_TIMEOUT, 0, $previous);
    }

    /**
     * 创建熔断器打开异常
     */
    public static function circuitOpen(string $providerName): self
    {
        return new self(
            "Circuit breaker is open for provider '{$providerName}'",
            self::TYPE_CIRCUIT_OPEN,
            503
        );
    }

    /**
     * 从 OpenAI 错误响应创建异常
     */
    public static function fromOpenAIError(array $error, int $statusCode): self
    {
        $message = $error['error']['message'] ?? $error['message'] ?? 'Unknown error';
        $type = $error['error']['type'] ?? $error['type'] ?? 'unknown';

        return match ($type) {
            'invalid_request_error' => self::invalidRequest($message, $error),
            'authentication_error' => self::authError($message, $error),
            'rate_limit_exceeded' => self::rateLimit($message, $error),
            'model_not_found' => self::modelNotFound($message, $error),
            'context_length_exceeded' => self::contextLengthExceeded(0, 0, $error),
            'content_filter' => self::contentFilter($message, $error),
            default => self::serverError($message, $statusCode, $error),
        };
    }

    /**
     * 从 Anthropic 错误响应创建异常
     */
    public static function fromAnthropicError(array $error, int $statusCode): self
    {
        $message = $error['error']['message'] ?? $error['message'] ?? 'Unknown error';
        $type = $error['error']['type'] ?? $error['type'] ?? 'unknown';

        return match ($type) {
            'invalid_request_error' => self::invalidRequest($message, $error),
            'authentication_error' => self::authError($message, $error),
            'rate_limit_error' => self::rateLimit($message, $error),
            'not_found_error' => self::modelNotFound($message, $error),
            'overloaded_error' => self::serverError($message, 503, $error),
            default => self::serverError($message, $statusCode, $error),
        };
    }

    /**
     * 判断错误是否可重试
     */
    public function isRetryable(): bool
    {
        return in_array($this->errorType, [
            self::TYPE_NETWORK_ERROR,
            self::TYPE_RATE_LIMIT,
            self::TYPE_SERVER_ERROR,
            self::TYPE_TIMEOUT,
        ]);
    }

    /**
     * 判断是否应跳过重试
     */
    public function shouldSkipRetry(): bool
    {
        return in_array($this->errorType, [
            self::TYPE_AUTH_ERROR,
            self::TYPE_INVALID_REQUEST,
            self::TYPE_MODEL_NOT_FOUND,
            self::TYPE_CONTEXT_LENGTH,
            self::TYPE_CONTENT_FILTER,
        ]);
    }

    /**
     * 获取 HTTP 状态码
     */
    public function getStatusCode(): int
    {
        return $this->code;
    }

    /**
     * 是否为渠道错误（应该对客户端隐藏详细错误信息）
     */
    public function isChannelError(): bool
    {
        // 认证错误、无效请求、模型未找到等属于渠道返回的错误
        // 这些错误应该记录日志，但返回 500 给客户端
        return in_array($this->errorType, [
            self::TYPE_AUTH_ERROR,
            self::TYPE_INVALID_REQUEST,
            self::TYPE_MODEL_NOT_FOUND,
            self::TYPE_CONTEXT_LENGTH,
            self::TYPE_CONTENT_FILTER,
            self::TYPE_RATE_LIMIT,
            self::TYPE_SERVER_ERROR,
        ]);
    }
}
