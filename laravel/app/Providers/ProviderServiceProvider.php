<?php

namespace App\Providers;

use App\Services\Provider\Driver\AnthropicProvider;
use App\Services\Provider\Driver\AzureProvider;
use App\Services\Provider\Driver\OpenAICompatibleProvider;
use App\Services\Provider\Driver\OpenAIProvider;
use App\Services\Provider\ProviderManager;
use Illuminate\Support\ServiceProvider;

class ProviderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderManager::class, function ($app) {
            $manager = new ProviderManager;
            $config = config('providers', []);

            $this->registerDefaultProviders($manager, $config);
            $this->registerCompatibleProviders($manager, $config);

            return $manager;
        });

        $this->app->alias(ProviderManager::class, 'provider.manager');
    }

    protected function registerDefaultProviders(ProviderManager $manager, array $config): void
    {
        $openaiConfig = $config['openai'] ?? [];
        if (! empty($openaiConfig['api_key'])) {
            $manager->register('openai', new OpenAIProvider($openaiConfig));
        }

        $anthropicConfig = $config['anthropic'] ?? [];
        if (! empty($anthropicConfig['api_key'])) {
            $manager->register('anthropic', new AnthropicProvider($anthropicConfig));
        }

        $azureConfig = $config['azure'] ?? [];
        if (! empty($azureConfig['api_key']) && ! empty($azureConfig['base_url'])) {
            $manager->register('azure', new AzureProvider($azureConfig));
        }
    }

    protected function registerCompatibleProviders(ProviderManager $manager, array $config): void
    {
        $compatibleServices = $config['compatible'] ?? [];

        foreach ($compatibleServices as $name => $serviceConfig) {
            if (! empty($serviceConfig['base_url'])) {
                $serviceConfig['name'] = $name;
                $manager->register($name, new OpenAICompatibleProvider($serviceConfig));
            }
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/providers.php' => config_path('providers.php'),
        ], 'config');
    }

    public function provides(): array
    {
        return [
            ProviderManager::class,
            'provider.manager',
        ];
    }
}
