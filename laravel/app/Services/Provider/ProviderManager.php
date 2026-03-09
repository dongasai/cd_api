<?php

namespace App\Services\Provider;

use App\Models\Channel;
use App\Services\Provider\Driver\AnthropicProvider;
use App\Services\Provider\Driver\OpenAICompatibleProvider;
use App\Services\Provider\Driver\OpenAIProvider;
use App\Services\Provider\Driver\ProviderInterface;
use App\Services\Provider\Exceptions\ProviderException;

/**
 * 供应商管理器
 *
 * 负责管理和解析AI服务供应商驱动实例
 */
class ProviderManager
{
    /**
     * 已注册的供应商
     *
     * @var array<string, ProviderInterface|callable|string>
     */
    protected array $providers = [];

    /**
     * 已解析的供应商实例缓存
     *
     * @var array<string, ProviderInterface>
     */
    protected array $resolved = [];

    /**
     * 注册供应商
     *
     * @param  string  $name  供应商名称
     * @param  ProviderInterface|callable|string  $provider  供应商实例、回调或类名
     */
    public function register(string $name, ProviderInterface|callable|string $provider): self
    {
        $this->providers[$name] = $provider;

        return $this;
    }

    /**
     * 获取供应商实例
     *
     * @param  string  $name  供应商名称
     * @return ProviderInterface 供应商实例
     *
     * @throws ProviderException 当供应商未注册或类型不正确时
     */
    public function get(string $name): ProviderInterface
    {
        // 从缓存获取已解析的实例
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        // 检查供应商是否已注册
        if (! isset($this->providers[$name])) {
            throw new ProviderException("Provider '{$name}' is not registered.");
        }

        $provider = $this->providers[$name];

        // 如果是回调函数，执行并获取结果
        if (is_callable($provider)) {
            $provider = $provider();
        }

        // 如果是类名字符串，从容器解析
        if (is_string($provider) && class_exists($provider)) {
            $provider = app($provider);
        }

        // 验证供应商实现了正确的接口
        if (! $provider instanceof ProviderInterface) {
            throw new ProviderException(
                "Provider '{$name}' must implement ProviderInterface"
            );
        }

        // 缓存已解析的实例
        $this->resolved[$name] = $provider;

        return $provider;
    }

    /**
     * 根据渠道配置获取供应商实例
     *
     * @param  Channel  $channel  渠道模型
     * @param  array  $clientHeaders  客户端请求头（用于转发）
     * @return ProviderInterface 供应商实例
     */
    public function getForChannel(Channel $channel, array $clientHeaders = []): ProviderInterface
    {
        $providerName = $channel->provider ?? $channel->provider_type ?? 'openai';

        $config = [
            'base_url' => $channel->base_url,
            'api_key' => $channel->api_key,
            'name' => $providerName,
            'forward_headers' => $channel->getForwardHeaderNames(),
            'client_headers' => $clientHeaders,
        ];

        return match ($providerName) {
            'openai' => new OpenAIProvider($config),
            'anthropic' => new AnthropicProvider($config),
            default => new OpenAICompatibleProvider($config),
        };
    }

    /**
     * 检查供应商是否已注册
     *
     * @param  string  $name  供应商名称
     */
    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * 获取所有已注册的供应商名称
     *
     * @return string[]
     */
    public function getRegisteredProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * 移除已注册的供应商
     *
     * @param  string  $name  供应商名称
     */
    public function forget(string $name): self
    {
        unset($this->providers[$name], $this->resolved[$name]);

        return $this;
    }

    /**
     * 清除所有已解析的供应商实例缓存
     */
    public function clearResolved(): self
    {
        $this->resolved = [];

        return $this;
    }

    /**
     * 批量注册供应商
     *
     * @param  array<string, ProviderInterface|callable|string>  $providers  供应商映射
     */
    public function extend(array $providers): self
    {
        foreach ($providers as $name => $provider) {
            $this->register($name, $provider);
        }

        return $this;
    }

    /**
     * 动态调用供应商
     *
     * 支持 providerName() 语法直接获取供应商实例
     *
     * @return mixed
     *
     * @throws \BadMethodCallException 当方法不存在时
     */
    public function __call(string $method, array $parameters)
    {
        if (isset($this->providers[$method])) {
            return $this->get($method);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
}
