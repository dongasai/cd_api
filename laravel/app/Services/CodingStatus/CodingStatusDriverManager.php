<?php

namespace App\Services\CodingStatus;

use App\Models\CodingAccount;
use App\Services\CodingStatus\Drivers\CodingStatusDriver;
use App\Services\CodingStatus\Drivers\GLMCodingStatusDriver;
use App\Services\CodingStatus\Drivers\PromptCodingStatusDriver;
use App\Services\CodingStatus\Drivers\RequestCodingStatusDriver;
use App\Services\CodingStatus\Drivers\SlidingRequestCodingStatusDriver;
use App\Services\CodingStatus\Drivers\SlidingTokenCodingStatusDriver;
use App\Services\CodingStatus\Drivers\TokenCodingStatusDriver;
use InvalidArgumentException;

/**
 * CodingStatus 驱动管理器
 *
 * 负责管理所有可用的驱动并提供驱动实例
 */
class CodingStatusDriverManager
{
    /**
     * 可用驱动映射
     *
     * @var array<string, class-string<CodingStatusDriver>>
     */
    protected array $drivers = [
        'TokenCodingStatus' => TokenCodingStatusDriver::class,
        'RequestCodingStatus' => RequestCodingStatusDriver::class,
        'PromptCodingStatus' => PromptCodingStatusDriver::class,
        'GLMCodingStatus' => GLMCodingStatusDriver::class,
        'SlidingTokenCodingStatus' => SlidingTokenCodingStatusDriver::class,
        'SlidingRequestCodingStatus' => SlidingRequestCodingStatusDriver::class,
    ];

    /**
     * 驱动实例缓存
     *
     * @var array<string, CodingStatusDriver>
     */
    protected array $instances = [];

    /**
     * 获取驱动实例
     */
    public function driver(string $driverClass, ?CodingAccount $account = null): CodingStatusDriver
    {
        // 如果传入的是简称，转换为完整类名
        if (isset($this->drivers[$driverClass])) {
            $driverClass = $this->drivers[$driverClass];
        }

        // 检查驱动类是否存在
        if (! class_exists($driverClass)) {
            throw new InvalidArgumentException("驱动类不存在: {$driverClass}");
        }

        // 检查是否实现了接口
        if (! in_array(CodingStatusDriver::class, class_implements($driverClass), true)) {
            throw new InvalidArgumentException("驱动类必须实现 CodingStatusDriver 接口: {$driverClass}");
        }

        // 创建实例
        $instance = new $driverClass;

        // 如果提供了账户，设置账户
        if ($account !== null) {
            $instance->setAccount($account);
        }

        return $instance;
    }

    /**
     * 为Coding账户获取对应的驱动
     */
    public function driverForAccount(CodingAccount $account): CodingStatusDriver
    {
        return $this->driver($account->driver_class, $account);
    }

    /**
     * 获取所有可用驱动
     *
     * @return array<string, array{name: string, class: string, description: string}>
     */
    public function getAvailableDrivers(): array
    {
        $result = [];

        foreach ($this->drivers as $name => $class) {
            /** @var CodingStatusDriver $instance */
            $instance = new $class;
            $result[$name] = [
                'name' => $instance->getName(),
                'class' => $class,
                'description' => $instance->getDescription(),
                'metrics' => $instance->getSupportedMetrics(),
            ];
        }

        return $result;
    }

    /**
     * 获取驱动选项 (用于表单选择)
     *
     * @return array<string, string>
     */
    public function getDriverOptions(): array
    {
        $options = [];

        foreach ($this->getAvailableDrivers() as $name => $info) {
            $options[$name] = $info['name'].' - '.$info['description'];
        }

        return $options;
    }

    /**
     * 注册新驱动
     *
     * @param  class-string<CodingStatusDriver>  $driverClass
     */
    public function registerDriver(string $name, string $driverClass): self
    {
        if (! class_exists($driverClass)) {
            throw new InvalidArgumentException("驱动类不存在: {$driverClass}");
        }

        if (! in_array(CodingStatusDriver::class, class_implements($driverClass), true)) {
            throw new InvalidArgumentException("驱动类必须实现 CodingStatusDriver 接口: {$driverClass}");
        }

        $this->drivers[$name] = $driverClass;

        return $this;
    }

    /**
     * 检查驱动是否存在
     */
    public function hasDriver(string $driverClass): bool
    {
        if (isset($this->drivers[$driverClass])) {
            return true;
        }

        return class_exists($driverClass) && in_array(
            CodingStatusDriver::class,
            class_implements($driverClass),
            true
        );
    }

    /**
     * 获取平台推荐的驱动
     *
     * @return array<string, string>
     */
    public function getRecommendedDriversForPlatform(string $platform): array
    {
        return match ($platform) {
            CodingAccount::PLATFORM_ALIYUN => ['RequestCodingStatus', 'SlidingRequestCodingStatus', 'TokenCodingStatus'],
            CodingAccount::PLATFORM_VOLCANO => ['RequestCodingStatus', 'SlidingRequestCodingStatus'],
            CodingAccount::PLATFORM_ZHIPU => ['PromptCodingStatus', 'GLMCodingStatus'],
            CodingAccount::PLATFORM_GITHUB => ['RequestCodingStatus', 'SlidingRequestCodingStatus'],
            CodingAccount::PLATFORM_CURSOR => ['RequestCodingStatus', 'SlidingRequestCodingStatus'],
            CodingAccount::PLATFORM_INFINI => ['SlidingRequestCodingStatus', 'SlidingTokenCodingStatus', 'RequestCodingStatus'],
            default => ['TokenCodingStatus', 'SlidingTokenCodingStatus'],
        };
    }

    /**
     * 获取驱动的默认配额配置
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(string $driverClass): array
    {
        $driver = $this->driver($driverClass);

        return $driver->getDefaultQuotaConfig();
    }

    /**
     * 获取驱动的配置表单字段
     *
     * @return array<int, array<string, mixed>>
     */
    public function getConfigFields(string $driverClass): array
    {
        $driver = $this->driver($driverClass);

        return $driver->getConfigFields();
    }
}
