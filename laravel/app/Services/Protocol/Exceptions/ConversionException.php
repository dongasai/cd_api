<?php

namespace App\Services\Protocol\Exceptions;

/**
 * 协议转换异常
 */
class ConversionException extends ProtocolException
{
    protected string $errorType = 'conversion_error';

    protected ?string $sourceProtocol = null;

    protected ?string $targetProtocol = null;

    public function __construct(
        string $message = '',
        int $code = 500,
        ?string $sourceProtocol = null,
        ?string $targetProtocol = null
    ) {
        parent::__construct($message, $code);
        $this->sourceProtocol = $sourceProtocol;
        $this->targetProtocol = $targetProtocol;
    }

    /**
     * 获取源协议
     */
    public function getSourceProtocol(): ?string
    {
        return $this->sourceProtocol;
    }

    /**
     * 获取目标协议
     */
    public function getTargetProtocol(): ?string
    {
        return $this->targetProtocol;
    }

    /**
     * 创建请求转换失败异常
     */
    public static function requestConversionFailed(
        string $sourceProtocol,
        string $targetProtocol,
        string $reason = ''
    ): self {
        $message = "Failed to convert request from {$sourceProtocol} to {$targetProtocol}";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self($message, 500, $sourceProtocol, $targetProtocol);
    }

    /**
     * 创建响应转换失败异常
     */
    public static function responseConversionFailed(
        string $sourceProtocol,
        string $targetProtocol,
        string $reason = ''
    ): self {
        $message = "Failed to convert response from {$sourceProtocol} to {$targetProtocol}";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self($message, 500, $sourceProtocol, $targetProtocol);
    }

    /**
     * 创建流式事件转换失败异常
     */
    public static function streamEventConversionFailed(
        string $sourceProtocol,
        string $targetProtocol,
        string $reason = ''
    ): self {
        $message = "Failed to convert stream event from {$sourceProtocol} to {$targetProtocol}";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self($message, 500, $sourceProtocol, $targetProtocol);
    }

    /**
     * 创建不支持的字段异常
     */
    public static function unsupportedField(
        string $field,
        string $sourceProtocol,
        string $targetProtocol
    ): self {
        return new self(
            "Field '{$field}' from {$sourceProtocol} is not supported in {$targetProtocol}",
            400,
            $sourceProtocol,
            $targetProtocol
        );
    }
}
