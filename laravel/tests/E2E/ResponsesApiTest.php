#!/usr/bin/env php
<?php

/**
 * OpenAI Responses API E2E 测试脚本
 *
 * 使用 OpenAI SDK 测试 CdApi 的 Responses API 端点
 * 运行: php tests/E2E/ResponsesApiTest.php
 */

require __DIR__.'/../../vendor/autoload.php';

use OpenAI\Client;

// 测试配置
const BASE_URL = 'http://192.168.4.107:32126/api';
const API_KEY = 'cdapi-K5bgxfbpHxcF0co1UxVUftjg3f5dzUBtTJrzzzc4Gm5RaL5R';
const TEST_MODEL = 'Qwen/Qwen3.5-397B-A17B'; // openai_response_test 测试专用模型

// 颜色输出
function green(string $text): string
{
    return "\033[32m{$text}\033[0m";
}

function red(string $text): string
{
    return "\033[31m{$text}\033[0m";
}

function yellow(string $text): string
{
    return "\033[33m{$text}\033[0m";
}

function blue(string $text): string
{
    return "\033[34m{$text}\033[0m";
}

// 创建 OpenAI 客户端
function createClient(): Client
{
    return \OpenAI::factory()
        ->withBaseUri(BASE_URL.'/openai/v1')
        ->withHttpHeader('Authorization', 'Bearer '.API_KEY)
        ->withHttpClient(new \GuzzleHttp\Client([
            'timeout' => 60,
            'connect_timeout' => 10,
        ]))
        ->make();
}

// 测试 1: 基础非流式请求
echo blue("\n=== 测试 1: 基础非流式请求 ===\n");

try {
    $client = createClient();

    $response = $client->responses()->create([
        'model' => TEST_MODEL,
        'input' => '你好，请简单介绍一下自己',
    ]);

    echo green("✓ 请求成功\n");
    echo '  Response ID: '.yellow($response->id)."\n";
    echo '  Object: '.$response->object."\n";
    echo '  Model: '.$response->model."\n";
    echo '  Output: '.mb_substr($response->output[0]->content[0]->text ?? 'N/A', 0, 100)."...\n";

    if ($response->usage) {
        echo '  Usage: input='.$response->usage->inputTokens.', output='.$response->usage->outputTokens."\n";
    }

    // 保存 response_id 用于后续测试
    $firstResponseId = $response->id;
} catch (\Exception $e) {
    echo red('✗ 请求失败: '.$e->getMessage())."\n";
    $firstResponseId = null;
}

// 测试 2: 状态管理 - 使用 previous_response_id
echo blue("\n=== 测试 2: 状态管理 (previous_response_id) ===\n");

if ($firstResponseId) {
    try {
        $client = createClient();

        $response = $client->responses()->create([
            'model' => TEST_MODEL,
            'input' => '我刚才问了什么？请复述我的问题。',
            'previous_response_id' => $firstResponseId,
        ]);

        echo green("✓ 状态管理请求成功\n");
        echo '  Response ID: '.yellow($response->id)."\n";
        echo '  Output: '.mb_substr($response->output[0]->content[0]->text ?? 'N/A', 0, 100)."...\n";

        // 验证是否包含之前的问题
        $output = $response->output[0]->content[0]->text ?? '';
        if (str_contains(strtolower($output), '介绍') || str_contains(strtolower($output), '自己')) {
            echo green("✓ 状态管理正常工作（模型记得之前的对话）\n");
        } else {
            echo yellow("? 模型可能不记得之前的对话，请手动检查输出\n");
        }
    } catch (\Exception $e) {
        echo red('✗ 状态管理请求失败: '.$e->getMessage())."\n";
    }
} else {
    echo yellow("⊘ 跳过（第一个测试未成功）\n");
}

// 测试 3: 流式请求
echo blue("\n=== 测试 3: 流式请求 ===\n");

try {
    $client = createClient();

    $stream = $client->responses()->createStreamed([
        'model' => TEST_MODEL,
        'input' => '请用一句话总结机器学习',
    ]);

    echo '  流式输出: ';
    $fullOutput = '';
    $chunkCount = 0;
    $streamResponseId = null;

    foreach ($stream as $chunk) {
        $chunkCount++;

        // 使用数组访问方式获取 type 字段
        $chunkArray = $chunk->toArray();
        $type = $chunkArray['type'] ?? null;

        if ($type === 'response.output_text.delta') {
            $text = $chunkArray['delta'] ?? '';
            echo $text;
            $fullOutput .= $text;
        }

        if ($type === 'response.completed') {
            $streamResponseId = $chunkArray['response']['id'] ?? null;
        }
    }

    echo "\n";
    echo green("✓ 流式请求完成\n");
    echo "  接收块数: {$chunkCount}\n";
    echo '  完整输出长度: '.strlen($fullOutput)." 字符\n";
    if ($streamResponseId) {
        echo '  Response ID: '.yellow($streamResponseId)."\n";
    }
} catch (\Exception $e) {
    echo red('✗ 流式请求失败: '.$e->getMessage())."\n";
}

// 测试 4: 带 instructions 的请求
echo blue("\n=== 测试 4: 带 instructions 的请求 ===\n");

try {
    $client = createClient();

    $response = $client->responses()->create([
        'model' => TEST_MODEL,
        'instructions' => '你是一个乐于助人的助手，请用中文回答所有问题。',
        'input' => 'Hello, how are you?',
    ]);

    echo green("✓ Instructions 请求成功\n");
    echo '  Response ID: '.yellow($response->id)."\n";
    echo '  Output: '.mb_substr($response->output[0]->content[0]->text ?? 'N/A', 0, 100)."...\n";

    // 检查是否用中文回答
    $output = $response->output[0]->content[0]->text ?? '';
    if (preg_match('/[\x{4e00}-\x{9fff}]/u', $output)) {
        echo green("✓ Instructions 生效（检测到中文回答）\n");
    } else {
        echo yellow("? 未检测到中文，请手动检查输出\n");
    }
} catch (\Exception $e) {
    echo red('✗ Instructions 请求失败: '.$e->getMessage())."\n";
}

// 测试 5: 数组格式 input
echo blue("\n=== 测试 5: 数组格式 input ===\n");

try {
    $client = createClient();

    $response = $client->responses()->create([
        'model' => TEST_MODEL,
        'input' => [
            ['role' => 'user', 'content' => '什么是深度学习？'],
        ],
    ]);

    echo green("✓ 数组格式 input 请求成功\n");
    echo '  Response ID: '.yellow($response->id)."\n";
    echo '  Output: '.mb_substr($response->output[0]->content[0]->text ?? 'N/A', 0, 100)."...\n";
} catch (\Exception $e) {
    echo red('✗ 数组格式 input 请求失败: '.$e->getMessage())."\n";
}

// 测试 6: 错误处理 - 无效 model
echo blue("\n=== 测试 6: 错误处理（无效 model）===\n");

try {
    $client = createClient();

    $response = $client->responses()->create([
        'model' => 'invalid-model-xyz',
        'input' => 'test',
    ]);

    echo red("✗ 应该抛出异常但没有\n");
} catch (\OpenAI\Exceptions\ErrorException $e) {
    echo green("✓ 正确处理错误\n");
    echo '  Error Type: '.($e->getErrorType() ?? 'N/A')."\n";
    echo '  Message: '.$e->getMessage()."\n";
} catch (\Exception $e) {
    echo green("✓ 抛出异常（符合预期）\n");
    echo '  Exception: '.get_class($e)."\n";
    echo '  Message: '.$e->getMessage()."\n";
}

// 测试 7: 空 tools + tool_choice（验证自动清空）
echo blue("\n=== 测试 7: 空 tools + tool_choice ===\n");

try {
    $client = createClient();

    // 发送空 tools + tool_choice，验证系统自动清空 tool_choice
    $response = $client->responses()->create([
        'model' => TEST_MODEL,
        'input' => '请告诉我今天天气如何',
        'tools' => [
            ['type' => 'function'],  // 无效的 tool（没有 function 定义）
        ],
        'tool_choice' => 'auto',
    ]);

    echo green("✓ 空 tools + tool_choice 请求成功（系统自动清空 tool_choice）\n");
    echo '  Response ID: '.yellow($response->id)."\n";
    echo '  Output: '.mb_substr($response->output[0]->content[0]->text ?? 'N/A', 0, 100)."...\n";
} catch (\Exception $e) {
    echo red('✗ 请求失败: '.$e->getMessage())."\n";
}

// 测试 8: 有效 tools + tool_choice
echo blue("\n=== 测试 8: 有效 tools + tool_choice ===\n");

try {
    $client = createClient();

    $response = $client->responses()->create([
        'model' => TEST_MODEL,
        'input' => '北京今天天气如何？',
        'tools' => [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_weather',
                    'description' => '获取指定城市的天气',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'city' => ['type' => 'string', 'description' => '城市名称'],
                        ],
                        'required' => ['city'],
                    ],
                ],
            ],
        ],
        'tool_choice' => 'auto',
    ]);

    echo green("✓ 有效 tools + tool_choice 请求成功\n");
    echo '  Response ID: '.yellow($response->id)."\n";

    // 检查是否有 tool_call
    $output = $response->output[0] ?? null;
    if ($output && isset($output->type) && $output->type === 'function_call') {
        echo green("✓ 模型正确调用工具\n");
        echo '  Tool: '.($output->name ?? 'N/A')."\n";
    } else {
        $text = $output->content[0]->text ?? 'N/A';
        echo '  Output: '.mb_substr($text, 0, 100)."...\n";
        echo yellow("  模型未调用工具（可能直接回答了）\n");
    }
} catch (\Exception $e) {
    echo red('✗ 请求失败: '.$e->getMessage())."\n";
}

// 总结
echo blue("\n=== 测试完成 ===\n");
echo "请检查上述输出以验证 Responses API 是否正常工作。\n";
echo "如果遇到问题，请查看 CdApi 的日志获取详细信息。\n\n";
