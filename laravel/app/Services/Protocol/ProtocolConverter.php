<?php

namespace App\Services\Protocol;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\Driver\DriverInterface;
use App\Services\Protocol\Exceptions\ConversionException;
use App\Services\Protocol\Exceptions\UnsupportedProtocolException;
use App\Services\Shared\DTO\StreamChunk;
use Generator;

/**
 * 协议转换器
 *
 * 提供协议结构体之间的转换能力
 * Shared\DTO 作为协议转换的中间层
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
     * 转换协议请求结构体
     *
     * 当源协议 != 目标协议时，通过 Shared\DTO 中间层进行转换
     *
     * @param  ProtocolRequest  $sourceRequest  源协议请求结构体
     * @param  string  $targetProtocol  目标协议名称
     * @return ProtocolRequest 目标协议请求结构体
     *
     * @throws ConversionException
     */
    public function convertRequest(
        ProtocolRequest $sourceRequest,
        string $targetProtocol
    ): ProtocolRequest {
        // 获取目标协议的请求类名
        $targetDriver = $this->driver($targetProtocol);
        $targetRequestClass = $this->getRequestClass($targetProtocol);

        // 如果是同协议，直接返回
        if ($sourceRequest instanceof $targetRequestClass) {
            return $sourceRequest;
        }

        // 需要转换：通过 Shared\DTO 中间层
        $sharedDTO = $sourceRequest->toSharedDTO();

        return $targetRequestClass::fromSharedDTO($sharedDTO);
    }

    /**
     * 转换协议响应结构体
     *
     * @param  ProtocolResponse  $sourceResponse  源协议响应结构体
     * @param  string  $targetProtocol  目标协议名称
     * @return ProtocolResponse 目标协议响应结构体
     *
     * @throws ConversionException
     */
    public function convertResponse(
        ProtocolResponse $sourceResponse,
        string $targetProtocol
    ): ProtocolResponse {
        // 获取目标协议的响应类名
        $targetResponseClass = $this->getResponseClass($targetProtocol);

        // 如果是同协议，直接返回
        if ($sourceResponse instanceof $targetResponseClass) {
            return $sourceResponse;
        }

        // 需要转换：通过 Shared\DTO 中间层
        $sharedDTO = $sourceResponse->toSharedDTO();

        return $targetResponseClass::fromSharedDTO($sharedDTO);
    }

    /**
     * 解析原始请求为协议请求结构体
     *
     * @param  array  $rawRequest  原始请求数据
     * @param  string  $protocol  协议名称
     * @return ProtocolRequest 协议请求结构体
     */
    public function normalizeRequest(array $rawRequest, string $protocol): ProtocolRequest
    {
        return $this->driver($protocol)->parseRequest($rawRequest);
    }

    /**
     * 从协议响应结构体构建响应数组
     *
     * @param  ProtocolResponse  $response  协议响应结构体
     * @param  string  $protocol  目标协议名称
     * @return array 响应数组
     */
    public function denormalizeResponse(ProtocolResponse $response, string $protocol): array
    {
        return $this->driver($protocol)->buildResponse($response);
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

    /**
     * 获取协议请求类名
     */
    protected function getRequestClass(string $protocol): string
    {
        $protocol = $this->normalizeProtocolName($protocol);

        return match ($protocol) {
            'openai_chat_completions' => \App\Services\Protocol\Driver\OpenAI\ChatCompletionRequest::class,
            'anthropic_messages' => \App\Services\Protocol\Driver\Anthropic\MessagesRequest::class,
            default => throw new UnsupportedProtocolException($protocol),
        };
    }

    /**
     * 获取协议响应类名
     */
    protected function getResponseClass(string $protocol): string
    {
        $protocol = $this->normalizeProtocolName($protocol);

        return match ($protocol) {
            'openai_chat_completions' => \App\Services\Protocol\Driver\OpenAI\ChatCompletionResponse::class,
            'anthropic_messages' => \App\Services\Protocol\Driver\Anthropic\MessagesResponse::class,
            default => throw new UnsupportedProtocolException($protocol),
        };
    }

    /**
     * 标准化协议名称
     *
     * 将简短协议名转换为完整协议名：
     * - anthropic -> anthropic_messages
     * - openai -> openai_chat_completions
     */
    protected function normalizeProtocolName(string $protocol): string
    {
        return match ($protocol) {
            'anthropic' => 'anthropic_messages',
            'openai' => 'openai_chat_completions',
            default => $protocol,
        };
    }
}
