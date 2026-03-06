<?php

namespace App\Services\Protocol;

use App\Services\Protocol\Driver\DriverInterface;
use App\Services\Protocol\Exceptions\UnsupportedProtocolException;

/**
 * 协议驱动管理器
 */
class DriverManager
{
    /**
     * 已注册的驱动
     *
     * @var array<string, DriverInterface>
     */
    protected array $drivers = [];

    /**
     * 已解析的驱动实例
     *
     * @var array<string, DriverInterface>
     */
    protected array $resolved = [];

    /**
     * 注册驱动
     */
    public function register(string $name, DriverInterface|callable|string $driver): self
    {
        $this->drivers[$name] = $driver;

        return $this;
    }

    /**
     * 获取驱动实例
     *
     * @throws UnsupportedProtocolException
     */
    public function driver(string $name): DriverInterface
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        if (! isset($this->drivers[$name])) {
            throw UnsupportedProtocolException::driverNotRegistered($name);
        }

        $driver = $this->drivers[$name];

        // 如果是回调，解析并缓存
        if (is_callable($driver)) {
            $driver = $driver();
        }

        // 如果是类名，实例化
        if (is_string($driver) && class_exists($driver)) {
            $driver = new $driver;
        }

        if (! $driver instanceof DriverInterface) {
            throw new UnsupportedProtocolException(
                "Driver for protocol '{$name}' must implement DriverInterface"
            );
        }

        $this->resolved[$name] = $driver;

        return $driver;
    }

    /**
     * 检查驱动是否已注册
     */
    public function hasDriver(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    /**
     * 获取所有已注册的驱动名称
     *
     * @return string[]
     */
    public function getRegisteredDrivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * 移除驱动
     */
    public function forget(string $name): self
    {
        unset($this->drivers[$name], $this->resolved[$name]);

        return $this;
    }

    /**
     * 清除所有已解析的驱动实例
     */
    public function clearResolved(): self
    {
        $this->resolved = [];

        return $this;
    }

    /**
     * 批量注册驱动
     */
    public function extend(array $drivers): self
    {
        foreach ($drivers as $name => $driver) {
            $this->register($name, $driver);
        }

        return $this;
    }

    /**
     * 动态调用驱动方法
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        // 支持 driverName() 语法获取驱动
        if (isset($this->drivers[$method])) {
            return $this->driver($method);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
}
