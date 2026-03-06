<?php

namespace App\Services\Protocol;

use App\Services\Protocol\Driver\DriverInterface;
use App\Services\Protocol\DTO\StandardRequest;
use App\Services\Protocol\DTO\StandardResponse;
use App\Services\Protocol\Exceptions\ConversionException;
use App\Services\Protocol\Exceptions\UnsupportedProtocolException;
use Generator;

/**
 * 协议转换器
 *
 * 提供便捷的协议转换方法
 */
class ProtocolConverter
{
    /**
     * 驱动管理器
     */
    protected DriverManager $driverManager;

    public function __construct(DriverManager $driverManager)
    {
        $this->driverManager = $driverManager;
    }

    /**
     * 获取驱动管理器
     */
    public function getDriverManager(): DriverManager
    {
        return $this->driverManager;
    }

    /**
     * 获取驱动
     */
    public function driver(string $protocol): DriverInterface
    {
        return $this->driverManager->driver($protocol);
    }

    /**
     * 转换请求
     *
     * @param  array  $rawRequest  原始请求
     * @param  string  $sourceProtocol  源协议
     * @param  string  $targetProtocol  目标协议
     * @return array 转换后的请求
     *
     * @throws ConversionException
     */
    public function convertRequest(
        array $rawRequest,
        string $sourceProtocol,
        string $targetProtocol
    ): array {
        // 如果协议相同，直接返回
        if ($sourceProtocol === $targetProtocol) {
            return $rawRequest;
        }

        try {
            // 解析源协议请求
            $sourceDriver = $this->driver($sourceProtocol);
            $standardRequest = $sourceDriver->parseRequest($rawRequest);

            // 构建目标协议请求
            $targetDriver = $this->driver($targetProtocol);

            return $standardRequest->toAnthropic();
        } catch (UnsupportedProtocolException $e) {
            throw ConversionException::requestConversionFailed(
                $sourceProtocol,
                $targetProtocol,
                $e->getMessage()
            );
        }
    }

    /**
     * 转换响应
     *
     * @param  array  $rawResponse  原始响应
     * @param  string  $sourceProtocol  源协议
     * @param  string  $targetProtocol  目标协议
     * @return array 转换后的响应
     *
     * @throws ConversionException
     */
    public function convertResponse(
        array $rawResponse,
        string $sourceProtocol,
        string $targetProtocol
    ): array {
        // 如果协议相同，直接返回
        if ($sourceProtocol === $targetProtocol) {
            return $rawResponse;
        }

        try {
            // 解析源协议响应
            $sourceDriver = $this->driver($sourceProtocol);
            $standardResponse = $sourceDriver->parseUpstreamResponse($rawResponse);

            // 构建目标协议响应
            $targetDriver = $this->driver($targetProtocol);

            return $targetDriver->buildResponse($standardResponse);
        } catch (UnsupportedProtocolException $e) {
            throw ConversionException::responseConversionFailed(
                $sourceProtocol,
                $targetProtocol,
                $e->getMessage()
            );
        }
    }

    /**
     * 转换流式事件
     *
     * @param  string  $rawEvent  原始事件
     * @param  string  $sourceProtocol  源协议
     * @param  string  $targetProtocol  目标协议
     * @return string|null 转换后的事件，null 表示忽略该事件
     *
     * @throws ConversionException
     */
    public function convertStreamEvent(
        string $rawEvent,
        string $sourceProtocol,
        string $targetProtocol
    ): ?string {
        // 如果协议相同，直接返回
        if ($sourceProtocol === $targetProtocol) {
            return $rawEvent;
        }

        try {
            $sourceDriver = $this->driver($sourceProtocol);
            $targetDriver = $this->driver($targetProtocol);

            // 解析源协议流式事件
            $standardEvent = $sourceDriver->parseStreamEvent($rawEvent);
            if ($standardEvent === null) {
                return null;
            }

            // 构建目标协议流式事件
            return $targetDriver->buildStreamChunk($standardEvent);
        } catch (UnsupportedProtocolException $e) {
            throw ConversionException::streamEventConversionFailed(
                $sourceProtocol,
                $targetProtocol,
                $e->getMessage()
            );
        }
    }

    /**
     * 批量转换流式事件
     *
     * @param  Generator  $stream  流式生成器
     * @param  string  $sourceProtocol  源协议
     * @param  string  $targetProtocol  目标协议
     */
    public function convertStream(
        Generator $stream,
        string $sourceProtocol,
        string $targetProtocol
    ): Generator {
        $sourceDriver = $this->driver($sourceProtocol);
        $targetDriver = $this->driver($targetProtocol);

        foreach ($stream as $rawEvent) {
            $standardEvent = $sourceDriver->parseStreamEvent($rawEvent);
            if ($standardEvent === null) {
                continue;
            }

            yield $targetDriver->buildStreamChunk($standardEvent);
        }

        // 发送结束标记
        yield $targetDriver->buildStreamDone();
    }

    /**
     * 转换请求到标准格式
     */
    public function normalizeRequest(array $rawRequest, string $protocol): StandardRequest
    {
        return $this->driver($protocol)->parseRequest($rawRequest);
    }

    /**
     * 从标准格式构建请求
     */
    public function denormalizeRequest(StandardRequest $standardRequest, string $protocol): array
    {
        return $standardRequest->toAnthropic();
    }

    /**
     * 转换响应到标准格式
     */
    public function normalizeResponse(array $rawResponse, string $protocol): StandardResponse
    {
        return $this->driver($protocol)->parseUpstreamResponse($rawResponse);
    }

    /**
     * 从标准格式构建响应
     */
    public function denormalizeResponse(StandardResponse $standardResponse, string $protocol): array
    {
        return $this->driver($protocol)->buildResponse($standardResponse);
    }

    /**
     * 构建错误响应
     */
    public function buildErrorResponse(
        string $message,
        string $type,
        int $code,
        string $protocol
    ): array {
        return $this->driver($protocol)->buildErrorResponse($message, $type, $code);
    }

    /**
     * 获取支持的协议列表
     */
    public function getSupportedProtocols(): array
    {
        return $this->driverManager->getRegisteredDrivers();
    }

    /**
     * 检查协议是否支持
     */
    public function isProtocolSupported(string $protocol): bool
    {
        return $this->driverManager->hasDriver($protocol);
    }
}
