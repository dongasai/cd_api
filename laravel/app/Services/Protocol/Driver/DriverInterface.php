<?php

namespace App\Services\Protocol\Driver;

use App\Services\Protocol\DTO\StandardRequest;
use App\Services\Protocol\DTO\StandardResponse;
use App\Services\Protocol\DTO\StandardStreamEvent;

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
     * 解析原始请求为标准格式
     *
     * @param  array  $rawRequest  原始请求数据
     * @return StandardRequest 标准请求格式
     */
    public function parseRequest(array $rawRequest): StandardRequest;

    /**
     * 从标准格式构建本协议的响应
     *
     * @param  StandardResponse  $standardResponse  标准响应格式
     * @return array 本协议的响应数据
     */
    public function buildResponse(StandardResponse $standardResponse): array;

    /**
     * 解析流式事件为标准格式
     * 用于解析上游返回的流式数据
     *
     * @param  string  $rawEvent  原始SSE事件数据
     * @return StandardStreamEvent|null 标准流式事件，null表示忽略该事件
     */
    public function parseStreamEvent(string $rawEvent): ?StandardStreamEvent;

    /**
     * 从标准格式构建本协议的流式块
     * 用于向客户端输出流式数据
     *
     * @param  StandardStreamEvent  $event  标准流式事件
     * @return string 本协议的SSE数据块
     */
    public function buildStreamChunk(StandardStreamEvent $event): string;

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

    /**
     * 从标准请求构建上游请求
     * 用于向目标协议的上游 API 发送请求
     *
     * @param  StandardRequest  $standardRequest  标准请求格式
     * @return array 本协议的请求数据
     */
    public function buildUpstreamRequest(StandardRequest $standardRequest): array;

    /**
     * 从上游响应解析标准响应
     * 用于解析上游 API 返回的响应数据
     *
     * @param  array  $response  上游响应数据
     * @return StandardResponse 标准响应格式
     */
    public function parseUpstreamResponse(array $response): StandardResponse;
}
