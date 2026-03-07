<?php

namespace App\Services\Provider;

use App\Models\Channel;
use App\Services\Provider\Driver\OpenAICompatibleProvider;
use App\Services\Provider\Driver\ProviderInterface;
use App\Services\Provider\Exceptions\ProviderException;

class ProviderManager
{
    protected array $providers = [];

    protected array $resolved = [];

    public function register(string $name, ProviderInterface|callable|string $provider): self
    {
        $this->providers[$name] = $provider;

        return $this;
    }

    public function get(string $name): ProviderInterface
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        if (! isset($this->providers[$name])) {
            throw new ProviderException("Provider '{$name}' is not registered.");
        }

        $provider = $this->providers[$name];

        if (is_callable($provider)) {
            $provider = $provider();
        }

        if (is_string($provider) && class_exists($provider)) {
            $provider = app($provider);
        }

        if (! $provider instanceof ProviderInterface) {
            throw new ProviderException(
                "Provider '{$name}' must implement ProviderInterface"
            );
        }

        $this->resolved[$name] = $provider;

        return $provider;
    }

    public function getForChannel(Channel $channel): ProviderInterface
    {
        $providerName = $channel->provider ?? $channel->provider_type ?? 'openai';

        $config = [
            'base_url' => $channel->base_url,
            'api_key' => $channel->api_key,
            'name' => $providerName,
        ];

        return new OpenAICompatibleProvider($config);
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    public function getRegisteredProviders(): array
    {
        return array_keys($this->providers);
    }

    public function forget(string $name): self
    {
        unset($this->providers[$name], $this->resolved[$name]);

        return $this;
    }

    public function clearResolved(): self
    {
        $this->resolved = [];

        return $this;
    }

    public function extend(array $providers): self
    {
        foreach ($providers as $name => $provider) {
            $this->register($name, $provider);
        }

        return $this;
    }

    public function __call(string $method, array $parameters)
    {
        if (isset($this->providers[$method])) {
            return $this->get($method);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
}
