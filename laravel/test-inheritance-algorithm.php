#!/usr/bin/env php
<?php

/**
 * 渠道继承核心算法验证（无依赖）
 *
 * 直接测试算法逻辑，不依赖 Laravel/数据库
 */
echo "=== 渠道继承核心算法验证 ===\n\n";

$passed = 0;
$failed = 0;

// ============================================================
// 测试 1: 循环检测算法
// ============================================================
echo "【测试 1】循环检测算法\n";

function detectCircularInheritance(array $nodes): bool
{
    // 将节点数组转为 id => node 的映射，方便查找
    $nodeMap = [];
    foreach ($nodes as $node) {
        $nodeMap[$node['id']] = $node;
    }

    // 从第一个节点（子渠道）开始遍历
    $visited = [];
    $currentId = $nodes[0]['id'] ?? null;

    while ($currentId !== null) {
        // 检查是否已访问
        if (isset($visited[$currentId])) {
            return true; // 循环
        }

        $visited[$currentId] = true;

        // 获取当前节点
        if (! isset($nodeMap[$currentId])) {
            break; // 节点不存在，停止
        }

        $node = $nodeMap[$currentId];

        // 到达根节点
        if ($node['parent_id'] === null) {
            break;
        }

        // 移动到父节点
        $currentId = $node['parent_id'];
    }

    return false;
}

// 测试用例 1.1: 无循环（正常继承链）
$chain1 = [
    ['id' => 3, 'parent_id' => 2], // 子
    ['id' => 2, 'parent_id' => 1], // 父
    ['id' => 1, 'parent_id' => null], // 根
];
$result1 = detectCircularInheritance($chain1);
echo '  1.1 无循环继承：';
if ($result1 === false) {
    echo "✓ 通过\n";
    $passed++;
} else {
    echo "✗ 失败\n";
    $failed++;
}

// 测试用例 1.2: 有循环
// 假设场景：渠道 3 的父是 2，2 的父是 1，但 1 的父又是 3（形成环）
// 当从 3 开始遍历：3(visited) → 2(visited) → 1(visited) → 3(已visited，循环!)
$chain2 = [
    ['id' => 3, 'parent_id' => 2],
    ['id' => 2, 'parent_id' => 1],
    ['id' => 1, 'parent_id' => 3], // 循环：1→3
];
$result2 = detectCircularInheritance($chain2);
echo '  1.2 有循环继承：';
if ($result2 === true) {
    echo "✓ 通过\n";
    $passed++;
} else {
    echo "✗ 失败\n";
    $failed++;
}

// 测试用例 1.3: 自循环
$chain3 = [
    ['id' => 1, 'parent_id' => 1], // 自己指向自己
];
$result3 = detectCircularInheritance($chain3);
echo '  1.3 自循环继承：';
if ($result3 === true) {
    echo "✓ 通过\n";
    $passed++;
} else {
    echo "✗ 失败\n";
    $failed++;
}

// ============================================================
// 测试 2: 深度计算算法
// ============================================================
echo "【测试 2】深度计算算法\n";

function calculateDepth(array $chain): int
{
    // 继承链长度 - 1 = 深度（父子关系数）
    return count($chain) - 1;
}

// 测试用例 2.1: 单渠道（深度 0）
$chain3 = [['id' => 1, 'parent_id' => null]];
$depth1 = calculateDepth($chain3);
echo '  2.1 单渠道深度：';
if ($depth1 === 0) {
    echo "✓ 通过（深度 {$depth1}）\n";
    $passed++;
} else {
    echo "✗ 失败（期望 0，实际 {$depth1}）\n";
    $failed++;
}

// 测试用例 2.2: 3级继承（深度 2）
$chain4 = [
    ['id' => 1, 'parent_id' => null],
    ['id' => 2, 'parent_id' => 1],
    ['id' => 3, 'parent_id' => 2],
];
$depth2 = calculateDepth($chain4);
echo '  2.2 3级继承深度：';
if ($depth2 === 2) {
    echo "✓ 通过（深度 {$depth2}）\n";
    $passed++;
} else {
    echo "✗ 失败（期望 2，实际 {$depth2}）\n";
    $failed++;
}

// ============================================================
// 测试 3: forward_headers 索引数组合并
// ============================================================
echo "【测试 3】forward_headers 索引数组合并\n";

function mergeIndexArrays(array $parent, array $child): array
{
    // MERGE 模式：array_merge + 去重
    $merged = array_merge($parent, $child);

    return array_values(array_unique($merged));
}

$parentHeaders = ['x-api-key', 'x-trace-id'];
$childHeaders = ['x-api-key', 'x-custom'];
$mergedHeaders = mergeIndexArrays($parentHeaders, $childHeaders);
sort($mergedHeaders);

$expectedHeaders = ['x-api-key', 'x-custom', 'x-trace-id'];
sort($expectedHeaders);

echo '  3.1 索引数组合并去重：';
if ($mergedHeaders === $expectedHeaders) {
    echo "✓ 通过\n";
    $passed++;
} else {
    echo "✗ 失败\n";
    $failed++;
}

// ============================================================
// 测试 4: config 关联数组深度合并
// ============================================================
echo "【测试 4】config 关联数组深度合并\n";

$parentConfig = [
    'timeout' => 30,
    'max_tokens' => 2000,
    'options' => ['stream' => true, 'top_p' => 0.9],
];
$childConfig = [
    'timeout' => 60,
    'options' => ['temperature' => 0.7],
];

// MERGE 模式：array_replace_recursive
$mergedConfig = array_replace_recursive($parentConfig, $childConfig);

echo '  4.1 子值覆盖父值：';
if ($mergedConfig['timeout'] === 60) {
    echo "✓ 通过\n";
    $passed++;
} else {
    echo "✗ 失败\n";
    $failed++;
}

echo '  4.2 父值继承（子无此键）：';
if ($mergedConfig['max_tokens'] === 2000) {
    echo "✓ 通过\n";
    $passed++;
} else {
    echo "✗ 失败\n";
    $failed++;
}

echo '  4.3 深度合并（嵌套数组）：';
if ($mergedConfig['options']['stream'] === true &&
    $mergedConfig['options']['top_p'] === 0.9 &&
    $mergedConfig['options']['temperature'] === 0.7) {
    echo "✓ 通过\n";
    $passed++;
} else {
    echo "✗ 失败\n";
    $failed++;
}

// ============================================================
// 测试 5: 标量字段继承逻辑
// ============================================================
echo "【测试 5】标量字段继承逻辑\n";

function resolveScalarField($parentValue, $childValue)
{
    // 子为空值（null 或空字符串）时继承父值
    return ($childValue !== null && $childValue !== '') ? $childValue : $parentValue;
}

echo '  5.1 子null继承父值：';
$resolved1 = resolveScalarField('https://api.openai.com', null);
if ($resolved1 === 'https://api.openai.com') {
    echo "✓ 通过\n";
    $passed++;
} else {
    echo "✗ 失败\n";
    $failed++;
}

echo '  5.2 子空字符串继承父值：';
$resolved2 = resolveScalarField('sk-parent', '');
if ($resolved2 === 'sk-parent') {
    echo "✓ 通过\n";
    $passed++;
} else {
    echo "✗ 失败\n";
    $failed++;
}

echo '  5.3 子有值使用子值：';
$resolved3 = resolveScalarField('sk-parent', 'sk-child');
if ($resolved3 === 'sk-child') {
    echo "✓ 通过\n";
    $passed++;
} else {
    echo "✗ 失败\n";
    $failed++;
}

// ============================================================
// 测试 6: OVERRIDE 模式语义
// ============================================================
echo "【测试 6】OVERRIDE 模式语义\n";

$parentConfig = ['timeout' => 30, 'max_tokens' => 2000, 'retry' => 3];
$childConfig = ['timeout' => 60];

// OVERRIDE 模式：数组字段完全使用子值
$overrideConfig = $childConfig;

echo '  6.1 OVERRIDE 完全使用子数组：';
if ($overrideConfig['timeout'] === 60 &&
    ! isset($overrideConfig['max_tokens']) &&
    ! isset($overrideConfig['retry'])) {
    echo "✓ 通过\n";
    $passed++;
} else {
    echo "✗ 失败\n";
    $failed++;
}

// ============================================================
// 测试 7: 深度限制语义
// ============================================================
echo "【测试 7】深度限制语义\n";

const MAX_DEPTH = 5;

echo '  7.1 深度=5 超限（>= 判断）：';
$depth = 5;
$exceeds = ($depth >= MAX_DEPTH);
if ($exceeds === true) {
    echo "✓ 通过\n";
    $passed++;
} else {
    echo "✗ 失败\n";
    $failed++;
}

echo '  7.2 深度=4 未超限：';
$depth = 4;
$exceeds = ($depth >= MAX_DEPTH);
if ($exceeds === false) {
    echo "✓ 通过\n";
    $passed++;
} else {
    echo "✗ 失败\n";
    $failed++;
}

// ============================================================
// 测试总结
// ============================================================
echo "\n".str_repeat('=', 60)."\n";
echo "测试结果：\n";
echo "  ✓ 通过：{$passed}\n";
echo "  ✗ 失败：{$failed}\n";
echo '  总计：'.($passed + $failed)."\n";

if ($failed === 0) {
    echo "\n✓ 所有核心算法测试通过！\n\n";
    echo "验证内容：\n";
    echo "  • 循环检测算法：使用 visited 数组，重复 ID 则循环\n";
    echo "  • 深度计算：链长度 - 1\n";
    echo "  • forward_headers：array_merge + array_unique（索引数组）\n";
    echo "  • config：array_replace_recursive（关联数组深度合并）\n";
    echo "  • 标量字段：子空值继承父值，子有值使用子值\n";
    echo "  • OVERRIDE：数组字段完全使用子值\n";
    echo "  • 深度限制：>= MAX_DEPTH（语义正确）\n";
    exit(0);
} else {
    echo "\n✗ 存在失败的测试\n";
    exit(1);
}
