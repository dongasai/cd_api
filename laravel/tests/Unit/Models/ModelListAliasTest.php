<?php

namespace Tests\Unit\Models;

use App\Models\ModelList;
use App\Services\ModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelListAliasTest extends TestCase
{
    use RefreshDatabase;

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
     * 测试 aliases 作为纯字符串数组存储
     */
    public function test_aliases_as_pure_string_array(): void
    {
        $model = ModelList::create([
            'model_name' => 'glm-4',
            'display_name' => 'GLM-4',
            'provider' => 'test',
            'aliases' => ['glm-4-plus', 'glm-4-turbo', 'glm-4-flash'], // 这些别名不需要存在
            'is_enabled' => true,
        ]);

        // 验证 aliases 正常存储
        $this->assertEquals(['glm-4-plus', 'glm-4-turbo', 'glm-4-flash'], $model->aliases);

        // 验证 getAllNames() 包含所有别名
        $allNames = $model->getAllNames();
        $this->assertContains('glm-4', $allNames);
        $this->assertContains('glm-4-plus', $allNames);
        $this->assertContains('glm-4-turbo', $allNames);
        $this->assertContains('glm-4-flash', $allNames);

        $model->delete();
    }

    /**
     * 测试别名不需要在 ModelList 中存在
     */
    public function test_aliases_not_require_existence(): void
    {
        // 创建模型，别名指向不存在的名称
        $model = ModelList::create([
            'model_name' => 'test-model',
            'display_name' => 'Test Model',
            'provider' => 'test',
            'aliases' => ['non-existent-alias-1', 'non-existent-alias-2'],
            'is_enabled' => true,
        ]);

        // 验证 aliases 正常存储，不会报错
        $model->refresh();
        $this->assertEquals(['non-existent-alias-1', 'non-existent-alias-2'], $model->aliases);

        // 验证不会自动创建别名对应的模型
        $this->assertNull(ModelList::where('model_name', 'non-existent-alias-1')->first());
        $this->assertNull(ModelList::where('model_name', 'non-existent-alias-2')->first());

        $model->delete();
    }

    /**
     * 测试通过别名查找模型
     */
    public function test_find_model_by_alias(): void
    {
        $model = ModelList::create([
            'model_name' => 'original-model',
            'display_name' => 'Original Model',
            'provider' => 'test',
            'aliases' => ['alias-name-1', 'alias-name-2'],
            'is_enabled' => true,
        ]);

        // 通过别名查找模型
        $foundModel = ModelService::findModelByAnyName('alias-name-1');
        $this->assertNotNull($foundModel);
        $this->assertEquals('original-model', $foundModel->model_name);

        // 通过另一个别名查找
        $foundModel2 = ModelService::findModelByAnyName('alias-name-2');
        $this->assertNotNull($foundModel2);
        $this->assertEquals('original-model', $foundModel2->model_name);

        // 通过原始名称查找
        $foundModel3 = ModelService::findModelByAnyName('original-model');
        $this->assertNotNull($foundModel3);
        $this->assertEquals('original-model', $foundModel3->model_name);

        $model->delete();
    }

    /**
     * 测试别名修改不会触发同步
     */
    public function test_alias_modification_no_sync(): void
    {
        // 创建两个模型
        $model1 = ModelList::create([
            'model_name' => 'model-a',
            'display_name' => 'Model A',
            'provider' => 'test',
            'is_enabled' => true,
        ]);

        $model2 = ModelList::create([
            'model_name' => 'model-b',
            'display_name' => 'Model B',
            'provider' => 'test',
            'is_enabled' => true,
        ]);

        // 设置 model1 的别名包含 model2 的名称
        $model1->aliases = ['model-b'];
        $model1->save();

        // 验证 model2 的 aliases 不会自动包含 model1 的名称
        $model2->refresh();
        $this->assertTrue(empty($model2->aliases) || $model2->aliases === []); // 应该为空，不会同步

        // 移除 model1 的别名
        $model1->aliases = [];
        $model1->save();

        // 验证 model2 仍然没有变化
        $model2->refresh();
        $this->assertTrue(empty($model2->aliases) || $model2->aliases === []);

        $model1->delete();
        $model2->delete();
    }
}
