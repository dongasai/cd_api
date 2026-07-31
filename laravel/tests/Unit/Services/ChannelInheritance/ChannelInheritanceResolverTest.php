<?php

namespace Tests\Unit\Services\ChannelInheritance;

use App\Enums\InheritMode;
use App\Models\Channel;
use App\Models\ChannelModel;
use App\Services\ChannelInheritance\ChannelInheritanceResolver;
use App\Services\ChannelInheritance\Exceptions\InheritanceResolverException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ChannelInheritanceResolver 单元测试
 *
 * 测试渠道继承解析器的核心功能，包括：
 * - 基础场景：无父渠道、一级继承、多级继承
 * - 边界场景：循环继承、深度超限、父渠道软删除、父渠道不存在
 * - 字段特定逻辑：标量字段、数组字段、模型列表
 */
class ChannelInheritanceResolverTest extends TestCase
{
    use RefreshDatabase;

    protected ChannelInheritanceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ChannelInheritanceResolver;
    }

    /* ==================== 基础场景测试 ==================== */

    /**
     * 测试无父渠道时返回自身配置
     */
    public function test_resolve_config_returns_self_config_when_no_parent(): void
    {
        $channel = Channel::factory()->create([
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com',
            'api_key' => 'sk-test123',
            'config' => ['timeout' => 30, 'retry' => 3],
            'forward_headers' => ['X-Custom-Header'],
            'parent_id' => null,
        ]);

        $config = $this->resolver->resolveConfig($channel);

        $this->assertEquals('openai', $config['provider']);
        $this->assertEquals('https://api.openai.com', $config['base_url']);
        $this->assertEquals('sk-test123', $config['api_key']);
        $this->assertEquals(['timeout' => 30, 'retry' => 3], $config['config']);
        $this->assertEquals(['X-Custom-Header'], $config['forward_headers']);
    }

    /**
     * 测试一级继承 MERGE 模式合并父子配置
     */
    public function test_single_level_merge_inheritance_merges_configs(): void
    {
        // 创建父渠道
        $parent = Channel::factory()->create([
            'provider' => 'openai',
            'base_url' => 'https://api.parent.com',
            'api_key' => 'sk-parent-key',
            'config' => [
                'timeout' => 30,
                'headers' => ['Authorization' => 'Bearer parent'],
                'parent_only' => 'value',
            ],
            'forward_headers' => ['X-Parent-Header'],
        ]);

        // 创建子渠道（MERGE 模式）
        $child = Channel::factory()->withParent($parent)->create([
            'inherit_mode' => InheritMode::MERGE,
            'provider' => null, // 继承父值
            'base_url' => null, // 继承父值
            'api_key' => 'sk-child-key', // 覆盖父值
            'config' => [
                'timeout' => 60, // 覆盖父值
                'headers' => ['X-Custom' => 'child'], // 追加
                'child_only' => 'child-value',
            ],
            'forward_headers' => ['X-Child-Header'], // 合并
        ]);

        $config = $this->resolver->resolveConfig($child);

        // 标量字段：子空值继承父值，非空值使用子值
        $this->assertEquals('openai', $config['provider']); // 继承
        $this->assertEquals('https://api.parent.com', $config['base_url']); // 继承
        $this->assertEquals('sk-child-key', $config['api_key']); // 子值

        // 数组字段 MERGE：array_replace_recursive 深度合并
        $this->assertEquals(60, $config['config']['timeout']); // 子覆盖父
        $this->assertEquals(['Authorization' => 'Bearer parent', 'X-Custom' => 'child'], $config['config']['headers']); // 合并
        $this->assertEquals('value', $config['config']['parent_only']); // 父保留
        $this->assertEquals('child-value', $config['config']['child_only']); // 子新增

        // forward_headers 也是数组字段，深度合并
        $this->assertEquals(['X-Parent-Header', 'X-Child-Header'], $config['forward_headers']);
    }

    /**
     * 测试一级继承 OVERRIDE 模式覆盖数组字段
     */
    public function test_single_level_override_inheritance_overrides_array_fields(): void
    {
        // 创建父渠道
        $parent = Channel::factory()->create([
            'provider' => 'openai',
            'base_url' => 'https://api.parent.com',
            'api_key' => 'sk-parent-key',
            'config' => [
                'timeout' => 30,
                'parent_only' => 'parent-value',
            ],
            'forward_headers' => ['X-Parent-Header'],
        ]);

        // 创建子渠道（OVERRIDE 模式）
        $child = Channel::factory()->withParent($parent)->create([
            'inherit_mode' => InheritMode::OVERRIDE,
            'provider' => 'anthropic', // 覆盖父值
            'base_url' => null, // 标量字段空值仍继承
            'api_key' => '', // 标量字段空值仍继承
            'config' => [
                'timeout' => 60, // 完全使用子值，父值不继承
            ],
            'forward_headers' => ['X-Child-Header'], // 完全使用子值
        ]);

        $config = $this->resolver->resolveConfig($child);

        // 标量字段：OVERRIDE 模式下子空值仍继承父值
        $this->assertEquals('anthropic', $config['provider']); // 子值
        $this->assertEquals('https://api.parent.com', $config['base_url']); // 继承（子空值）
        $this->assertEquals('sk-parent-key', $config['api_key']); // 继承（子空值）

        // 数组字段 OVERRIDE：完全使用子值
        $this->assertEquals(['timeout' => 60], $config['config']); // 仅子值
        $this->assertArrayNotHasKey('parent_only', $config['config']); // 父值未继承

        // forward_headers OVERRIDE
        $this->assertEquals(['X-Child-Header'], $config['forward_headers']); // 仅子值
    }

    /**
     * 测试多级继承（2-5级）逐级合并
     *
     * @dataProvider multiLevelInheritanceProvider
     */
    public function test_multi_level_inheritance_merges_config_properly(int $levels): void
    {
        // 创建根渠道
        $root = Channel::factory()->create([
            'provider' => 'openai',
            'base_url' => 'https://api.root.com',
            'api_key' => 'sk-root',
            'config' => ['level' => 'root', 'root_key' => 'root_value'],
        ]);

        $current = $root;
        $channels = [$root];

        // 创建中间层级
        for ($i = 1; $i < $levels; $i++) {
            $channel = Channel::factory()->withParent($current)->create([
                'inherit_mode' => InheritMode::MERGE,
                'provider' => null,
                'api_key' => null,
                'config' => ['level' => "level_{$i}", "key_{$i}" => "value_{$i}"],
            ]);
            $channels[] = $channel;
            $current = $channel;
        }

        $leaf = $current;
        $config = $this->resolver->resolveConfig($leaf);

        // 验证继承链构建正确
        $chain = $this->resolver->getInheritanceChain($leaf);
        $this->assertCount($levels, $chain);

        // 验证配置合并正确
        $this->assertEquals('openai', $config['provider']); // 从根继承
        $this->assertEquals('https://api.root.com', $config['base_url']); // 从根继承
        $this->assertEquals('sk-root', $config['api_key']); // 从根继承

        // 最后一级的 level 值
        $this->assertEquals("level_{$levels}", $config['config']['level']);

        // 验证所有层级的 key 都存在
        $this->assertEquals('root_value', $config['config']['root_key']);
        for ($i = 1; $i < $levels; $i++) {
            $this->assertEquals("value_{$i}", $config['config']["key_{$i}"]);
        }
    }

    /**
     * 多级继承数据提供器
     */
    public static function multiLevelInheritanceProvider(): array
    {
        return [
            '2 levels' => [2],
            '3 levels' => [3],
            '4 levels' => [4],
            '5 levels' => [5],
        ];
    }

    /* ==================== 边界场景测试 ==================== */

    /**
     * 测试循环继承检测抛出异常
     */
    public function test_circular_inheritance_throws_exception(): void
    {
        // 创建渠道 A
        $channelA = Channel::factory()->create([
            'provider' => 'openai',
            'base_url' => 'https://api.a.com',
            'parent_id' => null,
        ]);

        // 创建渠道 B，父为 A
        $channelB = Channel::factory()->create([
            'provider' => 'openai',
            'base_url' => 'https://api.b.com',
            'parent_id' => $channelA->id,
        ]);

        // 创建渠道 C，父为 B
        $channelC = Channel::factory()->create([
            'provider' => 'openai',
            'base_url' => 'https://api.c.com',
            'parent_id' => $channelB->id,
        ]);

        // 制造循环：将 A 的父设为 C（形成 A -> B -> C -> A 循环）
        $channelA->parent_id = $channelC->id;
        $channelA->save();

        $this->expectException(InheritanceResolverException::class);
        $this->expectExceptionMessage('循环继承');

        $this->resolver->getInheritanceChain($channelC);
    }

    /**
     * 测试深度超限（>5级）抛出异常
     */
    public function test_depth_exceeded_throws_exception(): void
    {
        // 创建6级继承链
        $channels = [];
        $parent = null;

        for ($i = 0; $i < 6; $i++) {
            $channel = Channel::factory()->create([
                'provider' => 'openai',
                'base_url' => "https://api.level{$i}.com",
                'parent_id' => $parent?->id,
            ]);
            $channels[] = $channel;
            $parent = $channel;
        }

        $leaf = $channels[5]; // 第6级

        $this->expectException(InheritanceResolverException::class);
        $this->expectExceptionMessage('继承深度超限');

        $this->resolver->getInheritanceChain($leaf);
    }

    /**
     * 测试父渠道软删除抛出异常
     */
    public function test_parent_soft_deleted_throws_exception(): void
    {
        // 创建父渠道并软删除
        $parent = Channel::factory()->create([
            'provider' => 'openai',
            'base_url' => 'https://api.parent.com',
            'deleted_at' => now(), // 软删除
        ]);

        // 创建子渠道
        $child = Channel::factory()->create([
            'provider' => 'openai',
            'base_url' => 'https://api.child.com',
            'parent_id' => $parent->id,
        ]);

        $this->expectException(InheritanceResolverException::class);
        $this->expectExceptionMessage('父渠道已被软删除');

        $this->resolver->getInheritanceChain($child);
    }

    /**
     * 测试父渠道不存在抛出异常
     */
    public function test_parent_not_found_throws_exception(): void
    {
        // 创建子渠道，指向不存在的父渠道 ID
        $child = Channel::factory()->create([
            'provider' => 'openai',
            'base_url' => 'https://api.child.com',
            'parent_id' => 99999, // 不存在的 ID
        ]);

        $this->expectException(InheritanceResolverException::class);
        $this->expectExceptionMessage('父渠道不存在');

        $this->resolver->getInheritanceChain($child);
    }

    /* ==================== 字段特定逻辑测试 ==================== */

    /**
     * 测试标量字段（base_url, api_key, provider）子空值继承父值
     *
     * @dataProvider scalarFieldsProvider
     */
    public function test_scalar_fields_inherit_when_child_empty(string $field, mixed $parentValue, mixed $childValue, mixed $expectedValue): void
    {
        $parent = Channel::factory()->create([
            $field => $parentValue,
        ]);

        $child = Channel::factory()->withParent($parent)->create([
            'inherit_mode' => InheritMode::MERGE,
            $field => $childValue,
        ]);

        $config = $this->resolver->resolveConfig($child);

        $this->assertEquals($expectedValue, $config[$field]);
    }

    /**
     * 标量字段测试数据提供器
     */
    public static function scalarFieldsProvider(): array
    {
        return [
            'base_url child null inherits parent' => ['base_url', 'https://parent.com', null, 'https://parent.com'],
            'base_url child empty inherits parent' => ['base_url', 'https://parent.com', '', 'https://parent.com'],
            'base_url child value uses child' => ['base_url', 'https://parent.com', 'https://child.com', 'https://child.com'],
            'api_key child null inherits parent' => ['api_key', 'sk-parent', null, 'sk-parent'],
            'api_key child empty inherits parent' => ['api_key', 'sk-parent', '', 'sk-parent'],
            'api_key child value uses child' => ['api_key', 'sk-parent', 'sk-child', 'sk-child'],
            'provider child null inherits parent' => ['provider', 'openai', null, 'openai'],
            'provider child empty inherits parent' => ['provider', 'openai', '', 'openai'],
            'provider child value uses child' => ['provider', 'openai', 'anthropic', 'anthropic'],
        ];
    }

    /**
     * 测试数组字段 MERGE 模式 array_replace_recursive 深度合并
     */
    public function test_array_fields_merge_mode_uses_array_replace_recursive(): void
    {
        $parent = Channel::factory()->create([
            'config' => [
                'level1' => [
                    'level2' => [
                        'key' => 'parent_value',
                        'parent_only' => 'exists',
                    ],
                ],
                'sibling' => 'kept',
            ],
            'forward_headers' => ['X-Parent' => 'parent'],
        ]);

        $child = Channel::factory()->withParent($parent)->create([
            'inherit_mode' => InheritMode::MERGE,
            'config' => [
                'level1' => [
                    'level2' => [
                        'key' => 'child_value', // 覆盖
                        'child_only' => 'new', // 新增
                    ],
                    'new_sibling' => 'added', // 新增
                ],
            ],
            'forward_headers' => ['X-Child' => 'child'],
        ]);

        $config = $this->resolver->resolveConfig($child);

        // 验证 array_replace_recursive 行为
        $this->assertEquals('child_value', $config['config']['level1']['level2']['key']); // 子覆盖父
        $this->assertEquals('exists', $config['config']['level1']['level2']['parent_only']); // 父保留
        $this->assertEquals('new', $config['config']['level1']['level2']['child_only']); // 子新增
        $this->assertEquals('kept', $config['config']['sibling']); // 父同级保留
        $this->assertEquals('added', $config['config']['level1']['new_sibling']); // 子新增

        // forward_headers 深度合并
        $this->assertEquals(['X-Parent' => 'parent', 'X-Child' => 'child'], $config['forward_headers']);
    }

    /**
     * 测试数组字段 OVERRIDE 模式完全使用子值
     */
    public function test_array_fields_override_mode_uses_child_only(): void
    {
        $parent = Channel::factory()->create([
            'config' => [
                'parent_key' => 'parent_value',
                'shared' => 'from_parent',
            ],
            'forward_headers' => ['X-Parent'],
        ]);

        $child = Channel::factory()->withParent($parent)->create([
            'inherit_mode' => InheritMode::OVERRIDE,
            'config' => [
                'child_key' => 'child_value',
                'shared' => 'from_child',
            ],
            'forward_headers' => ['X-Child'],
        ]);

        $config = $this->resolver->resolveConfig($child);

        // OVERRIDE 模式下数组字段完全使用子值
        $this->assertEquals([
            'child_key' => 'child_value',
            'shared' => 'from_child',
        ], $config['config']);
        $this->assertArrayNotHasKey('parent_key', $config['config']);

        $this->assertEquals(['X-Child'], $config['forward_headers']);
    }

    /**
     * 测试 channel_models 去重合并
     */
    public function test_channel_models_merge_mode_merges_with_deduplication(): void
    {
        $parent = Channel::factory()->create([
            'provider' => 'openai',
        ]);

        // 创建父渠道模型
        ChannelModel::factory()->create([
            'channel_id' => $parent->id,
            'model_name' => 'gpt-4',
            'display_name' => 'GPT-4 Parent',
            'is_enabled' => true,
            'is_default' => true,
            'mapped_model' => null,
        ]);

        ChannelModel::factory()->create([
            'channel_id' => $parent->id,
            'model_name' => 'gpt-3.5',
            'display_name' => 'GPT-3.5 Parent',
            'is_enabled' => true,
            'is_default' => false,
            'mapped_model' => null,
        ]);

        $child = Channel::factory()->withParent($parent)->create([
            'inherit_mode' => InheritMode::MERGE,
        ]);

        // 创建子渠道模型（覆盖 gpt-4）
        ChannelModel::factory()->create([
            'channel_id' => $child->id,
            'model_name' => 'gpt-4',
            'display_name' => 'GPT-4 Child Override',
            'is_enabled' => true,
            'is_default' => false,
            'mapped_model' => 'gpt-4-turbo',
        ]);

        ChannelModel::factory()->create([
            'channel_id' => $child->id,
            'model_name' => 'claude-3',
            'display_name' => 'Claude 3 Child',
            'is_enabled' => true,
            'is_default' => true,
            'mapped_model' => null,
        ]);

        $models = $this->resolver->resolveChannelModels($child);

        // 验证模型列表去重合并
        $this->assertCount(3, $models);

        $modelNames = array_column($models, 'model_name');
        $this->assertContains('gpt-4', $modelNames);
        $this->assertContains('gpt-3.5', $modelNames);
        $this->assertContains('claude-3', $modelNames);

        // 验证 gpt-4 被子渠道覆盖
        $gpt4Model = array_values(array_filter($models, fn ($m) => $m['model_name'] === 'gpt-4'))[0];
        $this->assertEquals('GPT-4 Child Override', $gpt4Model['display_name']);
        $this->assertEquals('gpt-4-turbo', $gpt4Model['mapped_model']);
    }

    /**
     * 测试 channel_models OVERRIDE 模式完全使用子渠道列表
     */
    public function test_channel_models_override_mode_uses_child_only(): void
    {
        $parent = Channel::factory()->create([
            'provider' => 'openai',
        ]);

        // 创建父渠道模型
        ChannelModel::factory()->create([
            'channel_id' => $parent->id,
            'model_name' => 'gpt-4',
            'display_name' => 'GPT-4',
            'is_enabled' => true,
        ]);

        $child = Channel::factory()->withParent($parent)->create([
            'inherit_mode' => InheritMode::OVERRIDE,
        ]);

        // 创建子渠道模型（完全不同）
        ChannelModel::factory()->create([
            'channel_id' => $child->id,
            'model_name' => 'claude-3',
            'display_name' => 'Claude 3',
            'is_enabled' => true,
        ]);

        $models = $this->resolver->resolveChannelModels($child);

        // OVERRIDE 模式下仅使用子渠道模型
        $this->assertCount(1, $models);
        $this->assertEquals('claude-3', $models[0]['model_name']);
    }

    /* ==================== 额外方法测试 ==================== */

    /**
     * 测试 resolveScalar 方法
     */
    public function test_resolve_scalar_returns_first_non_empty_value(): void
    {
        // 创建三级继承链
        $root = Channel::factory()->create([
            'base_url' => 'https://root.com',
            'api_key' => 'sk-root',
        ]);

        $middle = Channel::factory()->withParent($root)->create([
            'base_url' => null, // 继承 root
            'api_key' => 'sk-middle', // 覆盖
        ]);

        $leaf = Channel::factory()->withParent($middle)->create([
            'base_url' => 'https://leaf.com', // 覆盖
            'api_key' => null, // 继承 middle
        ]);

        // 测试 base_url：leaf 有值，直接返回
        $this->assertEquals('https://leaf.com', $this->resolver->resolveScalar($leaf, 'base_url'));

        // 测试 api_key：leaf 为空，查找第一个非空值（middle）
        $this->assertEquals('sk-middle', $this->resolver->resolveScalar($leaf, 'api_key'));

        // 测试 root 的字段访问
        $this->assertEquals('https://root.com', $this->resolver->resolveScalar($root, 'base_url'));
    }

    /**
     * 测试 resolveArray 方法
     */
    public function test_resolve_array_merges_through_chain(): void
    {
        $root = Channel::factory()->create([
            'config' => ['a' => 'root_a', 'root_only' => true],
        ]);

        $middle = Channel::factory()->withParent($root)->create([
            'inherit_mode' => InheritMode::MERGE,
            'config' => ['a' => 'middle_a', 'middle_only' => true],
        ]);

        $leaf = Channel::factory()->withParent($middle)->create([
            'inherit_mode' => InheritMode::MERGE,
            'config' => ['a' => 'leaf_a', 'leaf_only' => true],
        ]);

        $config = $this->resolver->resolveArray($leaf, 'config');

        // 逐级合并
        $this->assertEquals('leaf_a', $config['a']); // 最后覆盖
        $this->assertTrue($config['root_only']); // 根保留
        $this->assertTrue($config['middle_only']); // 中间保留
        $this->assertTrue($config['leaf_only']); // 叶保留
    }

    /**
     * 测试 hasCircularInheritance 方法
     */
    public function test_has_circular_inheritance_returns_boolean(): void
    {
        // 创建无循环的链
        $channelA = Channel::factory()->create(['parent_id' => null]);
        $channelB = Channel::factory()->create(['parent_id' => $channelA->id]);

        $this->assertFalse($this->resolver->hasCircularInheritance($channelB));

        // 制造循环
        $channelA->parent_id = $channelB->id;
        $channelA->save();

        $this->assertTrue($this->resolver->hasCircularInheritance($channelB));
    }

    /**
     * 测试 getInheritanceDepth 方法
     */
    public function test_get_inheritance_depth_returns_correct_depth(): void
    {
        // 根渠道深度为 0
        $root = Channel::factory()->create(['parent_id' => null]);
        $this->assertEquals(0, $this->resolver->getInheritanceDepth($root));

        // 一级子渠道深度为 1
        $child1 = Channel::factory()->create(['parent_id' => $root->id]);
        $this->assertEquals(1, $this->resolver->getInheritanceDepth($child1));

        // 二级子渠道深度为 2
        $child2 = Channel::factory()->create(['parent_id' => $child1->id]);
        $this->assertEquals(2, $this->resolver->getInheritanceDepth($child2));
    }

    /**
     * 测试 exceedsMaxDepth 方法
     */
    public function test_exceeds_max_depth_returns_boolean(): void
    {
        // 创建正好 5 层的链（不超）
        $channels = [];
        $parent = null;
        for ($i = 0; $i < 5; $i++) {
            $channel = Channel::factory()->create(['parent_id' => $parent?->id]);
            $channels[] = $channel;
            $parent = $channel;
        }

        // 5 层不超
        $this->assertFalse($this->resolver->exceedsMaxDepth($channels[4]));

        // 添加第 6 层
        $channel6 = Channel::factory()->create(['parent_id' => $channels[4]->id]);

        // 6 层超限
        $this->assertTrue($this->resolver->exceedsMaxDepth($channel6));
    }

    /**
     * 测试无效字段抛出异常
     */
    public function test_resolve_scalar_with_invalid_field_throws_exception(): void
    {
        $channel = Channel::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('无效的标量字段');

        $this->resolver->resolveScalar($channel, 'invalid_field');
    }

    /**
     * 测试无效数组字段抛出异常
     */
    public function test_resolve_array_with_invalid_field_throws_exception(): void
    {
        $channel = Channel::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('无效的数组字段');

        $this->resolver->resolveArray($channel, 'invalid_field');
    }
}
