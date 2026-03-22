<?php

namespace App\Services\Protocol\Driver;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Shared\DTO\StreamChunk;

/**
 * 协议驱动接口
 */
interface DriverInterface
{
    /**
     * 获取协议名称
     */
    public function getProtocolName(): string;

    /**
     * 解析原始请求为协议请求结构体
     *
     * @param  array  $rawRequest  原始请求数据
     * @return ProtocolRequest 协议请求结构体
     */
    public function parseRequest(array $rawRequest): ProtocolRequest;

    /**
     * 从协议响应结构体构建本协议的响应数组
     *
     * @param  ProtocolResponse  $response  协议响应结构体
     * @return array 本协议的响应数据
     */
    public function buildResponse(ProtocolResponse $response): array;

    /**
     * 从标准格式构建本协议的流式块
     * 用于向客户端输出流式数据
     *
     * @param  StreamChunk  $chunk  标准流式块
     * @return string 本协议的SSE数据块
     */
    public function buildStreamChunk(StreamChunk $chunk): string;

    /**
     * 构建流式结束标记
     */
    public function buildStreamDone(): string;

    /**
     * 验证请求格式
     */
    public function validateRequest(array $rawRequest): bool;

    /**
     * 获取请求中的模型名称
     */
    public function extractModel(array $rawRequest): string;

    /**
     * 判断是否需要协议转换
     *
     * @param  string  $targetProtocol  目标协议
     */
    public function needsConversion(string $targetProtocol): bool;

    /**
     * 构建错误响应
     *
     * @param  string  $message  错误消息
     * @param  string  $type  错误类型
     * @param  int  $code  错误码
     * @return array 错误响应
     */
    public function buildErrorResponse(string $message, string $type = 'error', int $code = 500): array;
}
