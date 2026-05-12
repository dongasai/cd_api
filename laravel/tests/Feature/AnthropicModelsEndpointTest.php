<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnthropicModelsEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试 Anthropic models endpoint 返回正确格式
     */
    public function test_anthropic_models_endpoint_returns_correct_format(): void
    {
        // 创建测试 API Key
        $apiKey = ApiKey::factory()->create([
            'status' => 'active',
            'name' => 'test-key',
        ]);

        $response = $this->withHeaders([
            'x-api-key' => $apiKey->key,
            'anthropic-version' => '2023-06-01',
        ])->getJson('/api/anthropic/v1/models');

        $response->assertStatus(200);

        // 验证响应结构符合 Anthropic 格式
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'display_name',
                    'created_at',
                ],
            ],
            'has_more',
        ]);

        // 验证 has_more 为 false
        $response->assertJson(['has_more' => false]);

        // 验证 type 字段为 'model'
        $data = $response->json('data');
        if (! empty($data)) {
            $this->assertEquals('model', $data[0]['type']);
        }
    }

    /**
     * 测试无 API Key 时返回 401
     */
    public function test_anthropic_models_requires_api_key(): void
    {
        $response = $this->getJson('/api/anthropic/v1/models');

        $response->assertStatus(401);
    }

    /**
     * 测试 /anthropic/models 路径也能正常工作
     */
    public function test_anthropic_models_path_works(): void
    {
        $apiKey = ApiKey::factory()->create([
            'status' => 'active',
            'name' => 'test-key',
        ]);

        $response = $this->withHeaders([
            'x-api-key' => $apiKey->key,
            'anthropic-version' => '2023-06-01',
        ])->getJson('/api/anthropic/models');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'has_more',
        ]);
    }
}
