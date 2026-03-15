<?php

namespace App\Services\Provider\Driver;

use App\Services\Shared\DTO\ActualRequestInfo;
use App\Services\Shared\DTO\Request;
use App\Services\Shared\DTO\Response;
use App\Services\Shared\DTO\StreamChunk;
use Generator;

/**
 * 供应商接口
 *
 * 定义所有 AI 服务供应商必须实现的方法
 */
interface ProviderInterface
{
    /**
     * 发送同步请求
     *
     * @param  Request  $request  统一请求对象
     * @return Response 统一响应对象
     *
     * @throws \App\Services\Provider\Exceptions\ProviderException
     */
    public function send(Request $request): Response;

    /**
     * 发送流式请求
     *
     * @param  Request  $request  统一请求对象
     * @return Generator<StreamChunk> 流式响应生成器
     *
     * @throws \App\Services\Provider\Exceptions\ProviderException
     */
    public function sendStream(Request $request): Generator;

    /**
     * 获取供应商支持的模型列表
     *
     * @return string[] 模型名称数组
     */
    public function getModels(): array;

    /**
     * 获取供应商名称
     *
     * @return string 供应商标识符
     */
    public function getProviderName(): string;

    /**
     * 执行健康检查
     *
     * @return bool 供应商是否健康可用
     */
    public function healthCheck(): bool;

    /**
     * 检查供应商是否可用
     *
     * 综合考虑熔断器状态、API 密钥配置等因素
     *
     * @return bool 供应商是否可用
     */
    public function isAvailable(): bool;

    /**
     * 获取最后一次错误消息
     *
     * @return string|null 错误消息，无错误时返回 null
     */
    public function getLastErrorMessage(): ?string;

    /**
     * 获取最后一次实际请求信息
     *
     * @return ActualRequestInfo|null 实际请求信息，无请求时返回 null
     */
    public function getLastRequestInfo(): ?ActualRequestInfo;
}
