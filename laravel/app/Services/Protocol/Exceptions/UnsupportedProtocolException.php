<?php

namespace App\Services\Protocol\Exceptions;

/**
 * 不支持的协议异常
 */
class UnsupportedProtocolException extends ProtocolException
{
    protected string $errorType = 'unsupported_protocol';

    public function __construct(string $protocol, array $supportedProtocols = [])
    {
        $message = "Unsupported protocol: {$protocol}";
        if (! empty($supportedProtocols)) {
            $message .= '. Supported protocols: '.implode(', ', $supportedProtocols);
        }
        parent::__construct($message, 400);
    }

    /**
     * 创建驱动未注册异常
     */
    public static function driverNotRegistered(string $protocol): self
    {
        return new self("Driver not registered for protocol: {$protocol}");
    }

    /**
     * 创建协议不支持操作异常
     */
    public static function operationNotSupported(string $operation, string $protocol): self
    {
        return new self("Operation '{$operation}' is not supported by protocol: {$protocol}");
    }
}
