<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyModelMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_model_returns_mapped_model(): void
    {
        $apiKey = ApiKey::factory()->create([
            'model_mappings' => [
                'cd-coding-latest' => 'gpt-4o',
                'cd-coding-fast' => 'gpt-4o-mini',
            ],
        ]);

        $this->assertEquals('gpt-4o', $apiKey->resolveModel('cd-coding-latest'));
        $this->assertEquals('gpt-4o-mini', $apiKey->resolveModel('cd-coding-fast'));
    }

    public function test_resolve_model_returns_original_when_not_mapped(): void
    {
        $apiKey = ApiKey::factory()->create([
            'model_mappings' => [
                'cd-coding-latest' => 'gpt-4o',
            ],
        ]);

        $this->assertEquals('gpt-4', $apiKey->resolveModel('gpt-4'));
        $this->assertEquals('claude-3-opus', $apiKey->resolveModel('claude-3-opus'));
    }

    public function test_resolve_model_returns_original_when_mappings_empty(): void
    {
        $apiKey = ApiKey::factory()->create([
            'model_mappings' => null,
        ]);

        $this->assertEquals('gpt-4', $apiKey->resolveModel('gpt-4'));
    }

    public function test_get_model_mappings_returns_empty_array_when_null(): void
    {
        $apiKey = ApiKey::factory()->create([
            'model_mappings' => null,
        ]);

        $this->assertEquals([], $apiKey->getModelMappings());
    }

    public function test_get_model_mappings_returns_mappings(): void
    {
        $mappings = [
            'cd-coding-latest' => 'gpt-4o',
            'cd-coding-fast' => 'gpt-4o-mini',
        ];

        $apiKey = ApiKey::factory()->create([
            'model_mappings' => $mappings,
        ]);

        $this->assertEquals($mappings, $apiKey->getModelMappings());
    }
}
