<?php

namespace Tests\Unit\Services\Router;

use App\Models\Channel;
use App\Services\Router\ProxyServer;
use App\Services\Shared\DTO\Request as SharedRequest;
use Tests\TestCase;

class ProxyServerModelMappingTest extends TestCase
{
    /**
     * 测试 body 透传模式下应用模型映射
     */
    public function test_model_mapping_applied_in_passthrough_mode(): void
    {
        $this->app->singleton(ProxyServer::class, function ($app) {
            return $app->make(ProxyServer::class);
        });
        $proxyServer = app(ProxyServer::class);

        // 创建模拟渠道
        $channel = new Channel([
            'provider' => 'anthropic',
            'base_url' => 'https://api.example.com',
        ]);
        $channel->config = ['body_passthrough' => true];

        // 创建标准请求
        $standardRequest = SharedRequest::fromArray([
            'model' => 'claude-sonnet-4-5',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        // 原始请求体（透传前的原始数据）
        $rawBodyString = json_encode([
            'model' => 'claude-sonnet-4-5',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
            'stream' => true,
        ]);

        // 使用反射调用 protected 方法
        $reflection = new \ReflectionClass($this->proxyServer);
        $method = $reflection->getMethod('buildProviderRequest');
        $method->setAccessible(true);

        // 调用方法，actualModel 是映射后的模型
        $providerRequest = $method->invoke(
            $this->proxyServer,
            $standardRequest,
            $channel,
            'anthropic',
            'qwen3.5-plus',  // 映射后的模型
            $rawBodyString
        );

        // 验证：请求体中的模型应该被替换为映射后的模型
        $decodedBody = json_decode($providerRequest->rawBodyString, true);
        $this->assertEquals('qwen3.5-plus', $decodedBody['model']);
        $this->assertEquals('Hello', $decodedBody['messages'][0]['content']);
        $this->assertTrue($decodedBody['stream']);
    }

    /**
     * 测试 body 透传模式下没有模型映射时保持原样
     */
    public function test_passthrough_without_model_mapping(): void
    {
        $proxyServer = app(ProxyServer::class);

        // 创建模拟渠道
        $channel = new Channel([
            'provider' => 'anthropic',
            'base_url' => 'https://api.example.com',
        ]);
        $channel->config = ['body_passthrough' => true];

        // 创建标准请求
        $standardRequest = SharedRequest::fromArray([
            'model' => 'claude-sonnet-4-5',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        // 原始请求体
        $rawBodyString = json_encode([
            'model' => 'claude-sonnet-4-5',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        // 使用反射调用 protected 方法
        $reflection = new \ReflectionClass($this->proxyServer);
        $method = $reflection->getMethod('buildProviderRequest');
        $method->setAccessible(true);

        // 调用方法，actualModel 与原模型相同（没有映射）
        $providerRequest = $method->invoke(
            $this->proxyServer,
            $standardRequest,
            $channel,
            'anthropic',
            'claude-sonnet-4-5',  // 没有映射，保持原样
            $rawBodyString
        );

        // 验证：请求体应该保持不变
        $decodedBody = json_decode($providerRequest->rawBodyString, true);
        $this->assertEquals('claude-sonnet-4-5', $decodedBody['model']);
    }

    /**
     * 测试非透传模式下模型映射正常工作
     */
    public function test_model_mapping_in_normal_mode(): void
    {
        $proxyServer = app(ProxyServer::class);

        // 创建模拟渠道（未开启透传）
        $channel = new Channel([
            'provider' => 'anthropic',
            'base_url' => 'https://api.example.com',
        ]);
        $channel->config = ['body_passthrough' => false];

        // 创建标准请求
        $standardRequest = SharedRequest::fromArray([
            'model' => 'claude-sonnet-4-5',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        // 使用反射调用 protected 方法
        $reflection = new \ReflectionClass($this->proxyServer);
        $method = $reflection->getMethod('buildProviderRequest');
        $method->setAccessible(true);

        // 调用方法
        $providerRequest = $method->invoke(
            $this->proxyServer,
            $standardRequest,
            $channel,
            'anthropic',
            'qwen3.5-plus',
            null  // 非透传模式，不传 rawBodyString
        );

        // 验证：模型应该被正确设置
        $this->assertEquals('qwen3.5-plus', $providerRequest->model);
    }
}
