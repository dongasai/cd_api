<?php

namespace App\Services\Protocol\Exceptions;

use Exception;

/**
 * 协议异常基类
 */
class ProtocolException extends Exception
{
    /**
     * 错误数据
     */
    protected array $data = [];

    /**
     * 错误类型
     */
    protected string $errorType = 'protocol_error';

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        array $data = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    /**
     * 获取错误数据
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 获取错误类型
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * 设置错误数据
     */
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * 创建缺少必填字段异常
     */
    public static function missingField(string $field, string $context = ''): self
    {
        $message = "Missing required field: {$field}";
        if ($context) {
            $message .= " in {$context}";
        }

        return new self($message, 400);
    }

    /**
     * 创建无效字段值异常
     */
    public static function invalidField(string $field, mixed $value, string $reason = ''): self
    {
        $message = "Invalid value for field '{$field}': ".json_encode($value);
        if ($reason) {
            $message .= ". {$reason}";
        }

        return new self($message, 400);
    }

    /**
     * 创建解析错误异常
     */
    public static function parseError(string $context, string $reason = ''): self
    {
        $message = "Failed to parse {$context}";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self($message, 400);
    }

    /**
     * 转换为 OpenAI 错误格式
     */
    public function toOpenAIError(): array
    {
        return [
            'error' => [
                'message' => $this->getMessage(),
                'type' => $this->errorType,
                'code' => $this->code > 0 ? $this->code : null,
            ],
        ];
    }

    /**
     * 转换为 Anthropic 错误格式
     */
    public function toAnthropicError(): array
    {
        return [
            'type' => 'error',
            'error' => [
                'type' => $this->errorType,
                'message' => $this->getMessage(),
            ],
        ];
    }
}
