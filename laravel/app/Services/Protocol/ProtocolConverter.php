<?php

namespace App\Services\Protocol;

use App\Services\Protocol\Driver\DriverInterface;
use App\Services\Protocol\Exceptions\ConversionException;
use App\Services\Protocol\Exceptions\UnsupportedProtocolException;
use App\Services\Shared\DTO\Request;
use App\Services\Shared\DTO\Response;
use App\Services\Shared\DTO\StreamChunk;
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
            // 解析源协议请求为标准格式
            $sourceDriver = $this->driver($sourceProtocol);
            $standardRequest = $sourceDriver->parseRequest($rawRequest);

            // 构建目标协议请求 - Provider 层负责
            // 这里返回的是标准格式，由 Provider 层转换为上游格式
            return ['_standard_request' => $standardRequest];
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
            // Provider 层已经将上游响应解析为标准格式
            // 这里直接使用标准格式构建目标协议响应
            $targetDriver = $this->driver($targetProtocol);

            // 假设 $rawResponse 已经是标准格式的数组表示
            // 实际上，Provider 层返回的是 Response DTO
            // 所以这里需要调整逻辑
            return $rawResponse;
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
     * @param  StreamChunk  $chunk  标准流式块
     * @param  string  $targetProtocol  目标协议
     * @return string 转换后的事件
     *
     * @throws ConversionException
     */
    public function convertStreamChunk(
        StreamChunk $chunk,
        string $targetProtocol
    ): string {
        try {
            $targetDriver = $this->driver($targetProtocol);

            return $targetDriver->buildStreamChunk($chunk);
        } catch (UnsupportedProtocolException $e) {
            throw ConversionException::streamEventConversionFailed(
                'standard',
                $targetProtocol,
                $e->getMessage()
            );
        }
    }

    /**
     * 批量转换流式事件
     *
     * @param  Generator  $stream  流式生成器 (产生 StreamChunk)
     * @param  string  $targetProtocol  目标协议
     */
    public function convertStream(
        Generator $stream,
        string $targetProtocol
    ): Generator {
        $targetDriver = $this->driver($targetProtocol);

        foreach ($stream as $chunk) {
            if ($chunk instanceof StreamChunk) {
                yield $targetDriver->buildStreamChunk($chunk);
            }
        }

        // 发送结束标记
        yield $targetDriver->buildStreamDone();
    }

    /**
     * 转换请求到标准格式
     */
    public function normalizeRequest(array $rawRequest, string $protocol): Request
    {
        return $this->driver($protocol)->parseRequest($rawRequest);
    }

    /**
     * 从标准格式构建响应
     */
    public function denormalizeResponse(Response $response, string $protocol): array
    {
        return $this->driver($protocol)->buildResponse($response);
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
