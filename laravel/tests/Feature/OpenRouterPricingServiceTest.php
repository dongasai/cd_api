<?php

namespace Tests\Feature;

use App\Services\Pricing\OpenRouterPricingService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OpenRouterPricingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('openrouter_models_data');
    }

    public function test_get_pricing_by_model_name(): void
    {
        $service = new OpenRouterPricingService;

        $pricing = $service->getPricingByModelName('openai/gpt-5.4-pro');

        $this->assertNotNull($pricing);
        $this->assertEquals('openai/gpt-5.4-pro', $pricing['model_id']);
        $this->assertArrayHasKey('prompt', $pricing);
        $this->assertArrayHasKey('completion', $pricing);
    }

    public function test_get_pricing_by_hugging_face_id(): void
    {
        $service = new OpenRouterPricingService;

        $pricing = $service->getPricingByHuggingFaceId('meta-llama/Llama-3.1-8B-Instruct');

        if ($pricing !== null) {
            $this->assertArrayHasKey('model_id', $pricing);
            $this->assertArrayHasKey('prompt', $pricing);
        } else {
            $this->markTestSkipped('No model found with the specified hugging_face_id');
        }
    }

    public function test_get_pricing_by_model_name_param(): void
    {
        $service = new OpenRouterPricingService;

        $pricing = $service->getPricing(null, 'openai/gpt-5.4');

        $this->assertNotNull($pricing);
        $this->assertEquals('openai/gpt-5.4', $pricing['model_id']);
    }

    public function test_get_pricing_by_both_params(): void
    {
        $service = new OpenRouterPricingService;

        $pricing = $service->getPricing('meta-llama/Llama-3.1-8B-Instruct', 'openai/gpt-5.4-pro');

        $this->assertNotNull($pricing);
        $this->assertEquals('openai/gpt-5.4-pro', $pricing['model_id']);
    }

    public function test_get_pricing_returns_null_for_nonexistent_model(): void
    {
        $service = new OpenRouterPricingService;

        $pricing = $service->getPricing(null, 'nonexistent/model');

        $this->assertNull($pricing);
    }

    public function test_models_data_is_cached(): void
    {
        $service = new OpenRouterPricingService;

        $models1 = $service->getModelsData();
        $models2 = $service->getModelsData();

        $this->assertEquals($models1, $models2);
        $this->assertNotEmpty($models1);
    }

    public function test_refresh_cache(): void
    {
        $service = new OpenRouterPricingService;

        $service->getModelsData();

        $result = $service->refreshCache();

        $this->assertTrue($result);
    }

    public function test_pricing_format(): void
    {
        $service = new OpenRouterPricingService;

        $pricing = $service->getPricing(null, 'openai/gpt-5.4-pro');

        $this->assertNotNull($pricing);
        $this->assertArrayHasKey('model_id', $pricing);
        $this->assertArrayHasKey('model_name', $pricing);
        $this->assertArrayHasKey('hugging_face_id', $pricing);
        $this->assertArrayHasKey('prompt', $pricing);
        $this->assertArrayHasKey('completion', $pricing);
        $this->assertArrayHasKey('context_length', $pricing);
        $this->assertArrayHasKey('raw_pricing', $pricing);
    }
}
