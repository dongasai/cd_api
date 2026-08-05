#!/usr/bin/env php
<?php

/**
 * 渠道继承功能验证脚本
 *
 * 使用 Laravel 应用容器测试核心继承逻辑
 */

// 初始化 Laravel 应用
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_DATABASE'] = 'cdapi_test';

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Enums\InheritMode;
use App\Models\Channel;
use App\Services\ChannelInheritance\ChannelInheritanceResolver;
use Illuminate\Contracts\Console\Kernel;

echo "=== 渠道继承功能验证脚本 ===\n\n";

// 初始化 Resolver
$resolver = new ChannelInheritanceResolver;

// 测试计数器
$passed = 0;
$failed = 0;

/**
 * 辅助函数：创建 Channel 模拟对象
 */
function createMockChannel(int $id, ?int $parentId = null, string $inheritMode = 'merge', array $config = []): Channel
{
    $channel = new Channel;
    $channel->id = $id;
    $channel->parent_id = $parentId;
    $channel->inherit_mode = InheritMode::from($inheritMode);
    $channel->base_url = $config['base_url'] ?? null;
    $channel->api_key = $config['api_key'] ?? null;
    $channel->provider = $config['provider'] ?? null;
    $channel->config = $config['config'] ?? [];
    $channel->forward_headers = $config['forward_headers'] ?? [];
    $channel->deleted_at = $config['deleted_at'] ?? null;

    return $channel;
}

/**
 * 测试用例：无父渠道
 */
echo '1. 测试无父渠道场景... ';
try {
    $channel = createMockChannel(1, null, 'merge', [
        'base_url' => 'https://api.openai.com',
        'api_key' => 'sk-test',
        'provider' => 'openai',
        'config' => ['timeout' => 30],
        'forward_headers' => ['x-api-key'],
    ]);

    $resolved = $resolver->resolveConfig($channel);

    if ($resolved['base_url'] === 'https://api.openai.com' &&
        $resolved['api_key'] === 'sk-test' &&
        $resolved['provider'] === 'openai') {
        echo "✓ 通过\n";
        $passed++;
    } else {
        echo "✗ 失败\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ 异常: {$e->getMessage()}\n";
    $failed++;
}

/**
 * 测试用例：一级 MERGE 继承
 */
echo '2. 测试一级 MERGE 继承... ';
try {
    $parent = createMockChannel(1, null, 'merge', [
        'base_url' => 'https://api.openai.com',
        'api_key' => 'sk-parent',
        'provider' => 'openai',
        'config' => ['timeout' => 30],
        'forward_headers' => ['x-api-key'],
    ]);

    $child = createMockChannel(2, 1, 'merge', [
        'api_key' => 'sk-child',
        'config' => ['max_tokens' => 1000],
    ]);

    // 模拟 parent 关系
    $child->setRelation('parent', $parent);

    $resolved = $resolver->resolveConfig($child);

    if ($resolved['base_url'] === 'https://api.openai.com' && // 继承父
        $resolved['api_key'] === 'sk-child' && // 使用子
        $resolved['provider'] === 'openai' && // 继承父
        isset($resolved['config']['timeout']) && // 合并配置
        $resolved['config']['timeout'] === 30) {
        echo "✓ 通过\n";
        $passed++;
    } else {
        echo "✗ 失败\n";
        var_dump($resolved);
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ 异常: {$e->getMessage()}\n";
    $failed++;
}

/**
 * 测试用例：一级 OVERRIDE 继承
 */
echo '3. 测试一级 OVERRIDE 继承... ';
try {
    $parent = createMockChannel(1, null, 'override', [
        'base_url' => 'https://api.openai.com',
        'api_key' => 'sk-parent',
        'provider' => 'openai',
        'config' => ['timeout' => 30, 'max_tokens' => 2000],
    ]);

    $child = createMockChannel(2, 1, 'override', [
        'api_key' => 'sk-child',
        'config' => ['timeout' => 60],
    ]);

    $child->setRelation('parent', $parent);

    $resolved = $resolver->resolveConfig($child);

    // OVERRIDE：标量字段仍继承空值，数组字段完全使用子值
    if ($resolved['base_url'] === 'https://api.openai.com' && // 标量继承
        $resolved['api_key'] === 'sk-child' && // 标量继承
        $resolved['provider'] === 'openai' && // 标量继承
        ! isset($resolved['config']['max_tokens']) && // 数组不继承，子没有此键
        $resolved['config']['timeout'] === 60) { // 数组使用子值
        echo "✓ 通过\n";
        $passed++;
    } else {
        echo "✗ 失败\n";
        var_dump($resolved);
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ 异常: {$e->getMessage()}\n";
    $failed++;
}

/**
 * 测试用例：forward_headers 索引数组合并（MERGE模式）
 */
echo '4. 测试 forward_headers 索引数组合并... ';
try {
    $parent = createMockChannel(1, null, 'merge', [
        'forward_headers' => ['x-api-key', 'x-trace-id'],
    ]);

    $child = createMockChannel(2, 1, 'merge', [
        'forward_headers' => ['x-api-key', 'x-custom'],
    ]);

    $child->setRelation('parent', $parent);

    $resolved = $resolver->resolveConfig($child);

    // forward_headers 应该去重合并
    $expectedHeaders = ['x-api-key', 'x-trace-id', 'x-custom'];
    sort($expectedHeaders);
    $actualHeaders = $resolved['forward_headers'] ?? [];
    sort($actualHeaders);

    if ($actualHeaders === $expectedHeaders) {
        echo "✓ 通过\n";
        $passed++;
    } else {
        echo '✗ 失败 (期望: '.json_encode($expectedHeaders).', 实际: '.json_encode($actualHeaders).")\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ 异常: {$e->getMessage()}\n";
    $failed++;
}

/**
 * 测试用例：循环继承检测
 */
echo '5. 测试循环继承检测... ';
try {
    $channel1 = createMockChannel(1, 3, 'merge');
    $channel2 = createMockChannel(2, 1, 'merge');
    $channel3 = createMockChannel(3, 2, 'merge');

    // 模拟循环：1 -> 3 -> 2 -> 1
    $channel1->setRelation('parent', $channel3);
    $channel3->setRelation('parent', $channel2);
    $channel2->setRelation('parent', $channel1);

    $hasCircular = $resolver->hasCircularInheritance($channel1);

    if ($hasCircular) {
        echo "✓ 通过\n";
        $passed++;
    } else {
        echo "✗ 失败 (应检测到循环)\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ 异常: {$e->getMessage()}\n";
    $failed++;
}

/**
 * 测试用例：深度计算
 */
echo '6. 测试继承深度计算... ';
try {
    $root = createMockChannel(1, null, 'merge');
    $level2 = createMockChannel(2, 1, 'merge');
    $level3 = createMockChannel(3, 2, 'merge');

    $level2->setRelation('parent', $root);
    $level3->setRelation('parent', $level2);

    $depth = $resolver->getInheritanceDepth($level3);

    if ($depth === 2) { // 从root到level3有2级继承关系
        echo "✓ 通过\n";
        $passed++;
    } else {
        echo "✗ 失败 (期望深度 2, 实际 {$depth})\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "✗ 异常: {$e->getMessage()}\n";
    $failed++;
}

// 输出测试结果
echo "\n=== 测试结果 ===\n";
echo "通过: {$passed}\n";
echo "失败: {$failed}\n";
echo '总计: '.($passed + $failed)."\n";

if ($failed === 0) {
    echo "\n✓ 所有测试通过！\n";
    exit(0);
} else {
    echo "\n✗ 存在失败的测试\n";
    exit(1);
}
