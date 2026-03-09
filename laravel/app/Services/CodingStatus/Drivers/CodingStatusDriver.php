<?php

namespace App\Services\CodingStatus\Drivers;

use App\Models\CodingAccount;

/**
 * CodingStatus 驱动接口
 *
 * 所有Coding状态驱动必须实现此接口
 */
interface CodingStatusDriver
{
    /**
     * 获取驱动名称
     */
    public function getName(): string;

    /**
     * 获取驱动描述
     */
    public function getDescription(): string;

    /**
     * 获取支持的计费维度
     *
     * @return array<string, string> 维度标识 => 维度名称
     */
    public function getSupportedMetrics(): array;

    /**
     * 设置Coding账户
     */
    public function setAccount(CodingAccount $account): self;

    /**
     * 获取当前配额状态
     *
     * @return array<string, mixed> 包含状态信息
     */
    public function getStatus(): array;

    /**
     * 检查配额是否充足
     *
     * @param  array<string, mixed>  $context  检查上下文 (model, tokens, requests等)
     * @return array<string, mixed> 检查结果
     */
    public function checkQuota(array $context): array;

    /**
     * 消耗配额
     *
     * @param  array<string, mixed>  $usage  使用量 (tokens, requests, prompts等)
     */
    public function consume(array $usage): void;

    /**
     * 判断是否应该禁用渠道
     */
    public function shouldDisable(): bool;

    /**
     * 判断是否应该启用渠道
     */
    public function shouldEnable(): bool;

    /**
     * 同步配额信息
     *
     * 从外部API或数据源同步最新配额状态
     */
    public function sync(): void;

    /**
     * 获取配额详细信息
     *
     * @return array<string, mixed>
     */
    public function getQuotaInfo(): array;

    /**
     * 获取周期信息
     *
     * @return array<string, mixed> 包含周期类型、开始时间、结束时间等
     */
    public function getPeriodInfo(): array;

    /**
     * 验证账户凭证
     *
     * @return array<string, mixed> 验证结果
     */
    public function validateCredentials(): array;

    /**
     * 获取配置表单字段
     *
     * @return array<int, array<string, mixed>> 表单字段定义
     */
    public function getConfigFields(): array;

    /**
     * 获取默认配额配置
     *
     * @return array<string, mixed>
     */
    public function getDefaultQuotaConfig(): array;

    /**
     * 获取检查间隔（秒）
     *
     * 用于定时任务决定检查频率，不同驱动类型有不同的推荐间隔
     * 滑动窗口驱动：较长间隔（如300秒），因为配额持续变化
     * 固定周期驱动：较短间隔（如60秒），需要在周期重置时及时检查
     *
     * @return int 检查间隔秒数
     */
    public function getCheckInterval(): int;
}
