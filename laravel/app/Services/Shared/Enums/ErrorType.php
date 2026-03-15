<?php

namespace App\Services\Shared\Enums;

/**
 * 错误类型枚举
 */
enum ErrorType: string
{
    // 认证错误
    case AuthenticationError = 'authentication_error';
    case InvalidApiKey = 'invalid_api_key';
    case InsufficientQuota = 'insufficient_quota';

    // 请求错误
    case InvalidRequest = 'invalid_request_error';
    case ContextLengthExceeded = 'context_length_exceeded';
    case RateLimitExceeded = 'rate_limit_exceeded';
    case ModelNotFound = 'model_not_found';

    // 服务器错误
    case InternalError = 'internal_error';
    case ServiceUnavailable = 'service_unavailable';
    case GatewayTimeout = 'gateway_timeout';

    // 内容错误
    case ContentPolicyViolation = 'content_policy_violation';

    /**
     * 获取错误类型的HTTP状态码
     */
    public function getHttpStatusCode(): int
    {
        return match ($this) {
            self::AuthenticationError,
            self::InvalidApiKey => 401,

            self::InsufficientQuota,
            self::RateLimitExceeded => 429,

            self::InvalidRequest,
            self::ContextLengthExceeded,
            self::ModelNotFound,
            self::ContentPolicyViolation => 400,

            self::InternalError => 500,
            self::ServiceUnavailable => 503,
            self::GatewayTimeout => 504,
        };
    }

    /**
     * 获取错误类型的可读描述
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::AuthenticationError => 'Authentication failed',
            self::InvalidApiKey => 'Invalid API key provided',
            self::InsufficientQuota => 'You exceeded your current quota',
            self::InvalidRequest => 'Invalid request parameters',
            self::ContextLengthExceeded => 'Context length exceeded limit',
            self::RateLimitExceeded => 'Rate limit exceeded',
            self::ModelNotFound => 'Model not found',
            self::InternalError => 'Internal server error',
            self::ServiceUnavailable => 'Service temporarily unavailable',
            self::GatewayTimeout => 'Gateway timeout',
            self::ContentPolicyViolation => 'Content policy violation',
        };
    }

    /**
     * 从 OpenAI 错误类型创建
     */
    public static function fromOpenAI(string $type): self
    {
        return match ($type) {
            'invalid_api_key' => self::InvalidApiKey,
            'insufficient_quota' => self::InsufficientQuota,
            'rate_limit_exceeded' => self::RateLimitExceeded,
            'context_length_exceeded' => self::ContextLengthExceeded,
            'model_not_found' => self::ModelNotFound,
            default => self::InvalidRequest,
        };
    }

    /**
     * 从 Anthropic 错误类型创建
     */
    public static function fromAnthropic(string $type): self
    {
        return match ($type) {
            'authentication_error' => self::AuthenticationError,
            'rate_limit_error' => self::RateLimitExceeded,
            'context_length_exceeded' => self::ContextLengthExceeded,
            'not_found_error' => self::ModelNotFound,
            'overloaded_error' => self::ServiceUnavailable,
            default => self::InvalidRequest,
        };
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): string
    {
        return match ($this) {
            self::AuthenticationError => 'invalid_api_key',
            self::InsufficientQuota => 'insufficient_quota',
            self::RateLimitExceeded => 'rate_limit_exceeded',
            self::ContextLengthExceeded => 'context_length_exceeded',
            self::ModelNotFound => 'model_not_found',
            self::ServiceUnavailable => 'server_error',
            default => 'invalid_request_error',
        };
    }

    /**
     * 转换为 Anthropic 格式
     */
    public function toAnthropic(): string
    {
        return match ($this) {
            self::AuthenticationError => 'authentication_error',
            self::RateLimitExceeded => 'rate_limit_error',
            self::ContextLengthExceeded => 'context_length_exceeded',
            self::ModelNotFound => 'not_found_error',
            self::ServiceUnavailable => 'overloaded_error',
            default => 'invalid_request_error',
        };
    }
}
