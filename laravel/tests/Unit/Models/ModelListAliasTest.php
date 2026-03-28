<?php

namespace Tests\Unit\Models;

use App\Models\ModelList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelListAliasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试设置别名后，关联模型自动包含反向别名
     */
    public function test_alias_sync_to_related_models(): void
    {
        // 创建两个测试模型
        $model1 = ModelList::create([
            'model_name' => 'test-model-1',
            'display_name' => 'Test Model 1',
            'provider' => 'test',
            'is_enabled' => true,
        ]);

        $model2 = ModelList::create([
            'model_name' => 'test-model-2',
            'display_name' => 'Test Model 2',
            'provider' => 'test',
            'is_enabled' => true,
        ]);

        // 设置 model1 的别名包含 model2 的名称
        $model1->aliases = ['test-model-2'];
        $model1->save();

        // 验证 model2 的 aliases 自动包含 model1 的名称
        $model2->refresh();
        $this->assertEquals(['test-model-1'], $model2->aliases);

        // 清理测试数据
        $model1->aliases = [];
        $model1->save();
        $model2->aliases = [];
        $model2->save();
    }

    /**
     * 测试 getAllNames() 方法返回正确的集合
     */
    public function test_get_all_names_method(): void
    {
        $model = ModelList::create([
            'model_name' => 'glm-5',
            'display_name' => 'GLM-5',
            'provider' => 'alibaba',
            'aliases' => ['GLM-5', 'z-ai/glm-5'],
            'is_enabled' => true,
        ]);

        $allNames = $model->getAllNames();

        // 验证包含模型自身名称和所有别名
        $this->assertContains('glm-5', $allNames);
        $this->assertContains('GLM-5', $allNames);
        $this->assertContains('z-ai/glm-5', $allNames);

        // 验证数量正确
        $this->assertCount(3, $allNames);

        // 验证没有重复
        $this->assertCount(3, array_unique($allNames));

        // 清理
        $model->delete();
    }

    /**
     * 测试别名移除时的对称性同步
     */
    public function test_alias_removal_sync(): void
    {
        // 创建两个测试模型
        $model1 = ModelList::create([
            'model_name' => 'test-a',
            'display_name' => 'Test A',
            'provider' => 'test',
            'is_enabled' => true,
        ]);

        $model2 = ModelList::create([
            'model_name' => 'test-b',
            'display_name' => 'Test B',
            'provider' => 'test',
            'is_enabled' => true,
        ]);

        // 设置别名
        $model1->aliases = ['test-b'];
        $model1->save();

        // 验证双向同步
        $model2->refresh();
        $this->assertEquals(['test-a'], $model2->aliases);

        // 移除别名
        $model1->aliases = [];
        $model1->save();

        // 验证 model2 的别名也被移除
        $model2->refresh();
        $this->assertEquals([], $model2->aliases);

        // 清理
        $model1->delete();
        $model2->delete();
    }

    /**
     * 测试自引用被跳过
     */
    public function test_self_reference_skipped(): void
    {
        $model = ModelList::create([
            'model_name' => 'test-self',
            'display_name' => 'Test Self',
            'provider' => 'test',
            'is_enabled' => true,
        ]);

        // 设置别名包含自己的名称
        $model->aliases = ['test-self', 'other-alias'];
        $model->save();

        // 验证 aliases 中仍然包含自己（允许保存）
        // 但不会尝试查找 test-self 模型进行同步（避免循环）
        $model->refresh();
        $this->assertContains('test-self', $model->aliases);

        // 清理
        $model->delete();
    }
}
