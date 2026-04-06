<?php

namespace App\Services\Search;

use App\Models\SearchDriver as SearchDriverModel;
use App\Models\SearchLog;
use App\Services\Search\Driver\SearchDriverInterface;
use App\Services\Search\Exceptions\SearchDriverException;
use Illuminate\Support\Facades\Config;

/**
 * 搜索驱动管理器
 *
 * 支持从数据库读取驱动配置
 */
class SearchDriverManager
{
    /**
     * 已注册的驱动
     *
     * @var array<string, class-string<SearchDriverInterface>|callable>
     */
    protected array $drivers = [];

    /**
     * 已解析的驱动实例
     *
     * @var array<string, SearchDriverInterface>
     */
    protected array $resolved = [];

    /**
     * 默认驱动
     */
    protected string $defaultDriver;

    /**
     * 是否使用数据库配置
     */
    protected bool $useDatabase = true;

    public function __construct()
    {
        $this->defaultDriver = Config::get('search.default', 'mock');

        // 尝试从数据库加载驱动配置
        $this->loadFromDatabase();
    }

    /**
     * 从数据库加载驱动配置
     */
    protected function loadFromDatabase(): void
    {
        try {
            $dbDrivers = SearchDriverModel::where('status', SearchDriverModel::STATUS_ACTIVE)
                ->orderByDesc('priority')
                ->get();

            foreach ($dbDrivers as $dbDriver) {
                $this->drivers[$dbDriver->slug] = [
                    'class' => $dbDriver->driver_class,
                    'config' => $dbDriver->config ?? [],
                    'timeout' => $dbDriver->timeout,
                    'model' => $dbDriver,
                ];

                // 设置默认驱动
                if ($dbDriver->is_default) {
                    $this->defaultDriver = $dbDriver->slug;
                }
            }

            // 如果数据库中没有驱动，使用配置文件
            if (empty($this->drivers)) {
                $this->useDatabase = false;
                $this->registerBuiltInDrivers();
            }
        } catch (\Exception $e) {
            // 数据库不可用时，使用配置文件
            $this->useDatabase = false;
            $this->registerBuiltInDrivers();
        }
    }

    /**
     * 注册内置驱动（从配置文件）
     */
    protected function registerBuiltInDrivers(): void
    {
        $drivers = Config::get('search.drivers', []);

        foreach ($drivers as $name => $config) {
            if (isset($config['driver']) && class_exists($config['driver'])) {
                $this->drivers[$name] = $config['driver'];
            }
        }

        // 默认注册 Mock 驱动
        if (! $this->hasDriver('mock')) {
            $this->drivers['mock'] = \App\Services\Search\Driver\MockSearchDriver::class;
        }
    }

    /**
     * 注册驱动
     */
    public function register(string $name, SearchDriverInterface|callable|string|array $driver): self
    {
        $this->drivers[$name] = $driver;

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
     * 获取驱动实例
     *
     * @throws SearchDriverException
     */
    public function driver(?string $name = null): SearchDriverInterface
    {
        $name = $name ?? $this->defaultDriver;

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        if (! isset($this->drivers[$name])) {
            throw SearchDriverException::driverNotRegistered($name);
        }

        $driverConfig = $this->drivers[$name];

        // 支持数组配置（数据库模式）
        if (is_array($driverConfig) && isset($driverConfig['class'])) {
            $driverClass = $driverConfig['class'];
            $config = $driverConfig['config'] ?? [];
            $config['timeout'] = $driverConfig['timeout'] ?? 30;

            if (! class_exists($driverClass)) {
                throw SearchDriverException::driverNotRegistered($name);
            }

            $driver = new $driverClass($config);
        } elseif (is_callable($driverConfig)) {
            $driver = $driverConfig();
        } elseif (is_string($driverConfig) && class_exists($driverConfig)) {
            $config = Config::get('search.drivers.'.$name, []);
            $driver = new $driverConfig($config);
        } else {
            $driver = $driverConfig;
        }

        if (! $driver instanceof SearchDriverInterface) {
            throw new SearchDriverException(
                "Driver '{$name}' must implement SearchDriverInterface"
            );
        }

        $this->resolved[$name] = $driver;

        return $driver;
    }

    /**
     * 获取驱动模型（数据库模式）
     */
    public function getDriverModel(string $name): ?SearchDriverModel
    {
        $driverConfig = $this->drivers[$name];

        if (is_array($driverConfig) && isset($driverConfig['model'])) {
            return $driverConfig['model'];
        }

        return null;
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
     * 获取默认驱动名称
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /**
     * 设置默认驱动
     */
    public function setDefaultDriver(string $name): self
    {
        $this->defaultDriver = $name;

        return $this;
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
     * 刷新驱动配置（重新从数据库加载）
     */
    public function refresh(): self
    {
        $this->drivers = [];
        $this->resolved = [];
        $this->loadFromDatabase();

        return $this;
    }

    /**
     * 是否使用数据库配置
     */
    public function isUsingDatabase(): bool
    {
        return $this->useDatabase;
    }

    /**
     * 动态调用默认驱动方法
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->{$method}(...$parameters);
    }
}