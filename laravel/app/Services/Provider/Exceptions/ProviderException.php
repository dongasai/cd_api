<?php

namespace App\Services\Provider\Exceptions;

class ProviderException extends \RuntimeException
{
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

    protected string $errorType;
    protected ?array $rawError = null;

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

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function getRawError(): ?array
    {
        return $this->rawError;
    }

    public static function networkError(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, self::TYPE_NETWORK_ERROR, 0, $previous);
    }

    public static function authError(string $message, ?array $rawError = null): self
    {
        return new self($message, self::TYPE_AUTH_ERROR, 401, null, $rawError);
    }

    public static function rateLimit(string $message, ?array $rawError = null): self
    {
        return new self($message, self::TYPE_RATE_LIMIT, 429, null, $rawError);
    }

    public static function invalidRequest(string $message, ?array $rawError = null): self
    {
        return new self($message, self::TYPE_INVALID_REQUEST, 400, null, $rawError);
    }

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

    public static function contentFilter(string $message, ?array $rawError = null): self
    {
        return new self($message, self::TYPE_CONTENT_FILTER, 400, null, $rawError);
    }

    public static function serverError(string $message, int $code = 500, ?array $rawError = null): self
    {
        return new self($message, self::TYPE_SERVER_ERROR, $code, null, $rawError);
    }

    public static function timeout(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, self::TYPE_TIMEOUT, 0, $previous);
    }

    public static function circuitOpen(string $providerName): self
    {
        return new self(
            "Circuit breaker is open for provider '{$providerName}'",
            self::TYPE_CIRCUIT_OPEN,
            503
        );
    }

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

    public function isRetryable(): bool
    {
        return in_array($this->errorType, [
            self::TYPE_NETWORK_ERROR,
            self::TYPE_RATE_LIMIT,
            self::TYPE_SERVER_ERROR,
            self::TYPE_TIMEOUT,
        ]);
    }

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
}
