<?php

namespace Database\Seeders;

use App\Models\ChannelAffinityRule;
use Illuminate\Database\Seeder;

class ChannelAffinityRuleSeeder extends Seeder
{
    /**
     * 渠道亲和性规则种子数据
     *
     * 使用 updateOrCreate 确保规则唯一性，基于 name 作为唯一标识
     */
    public function run(): void
    {
        $rules = [
            [
                'name' => 'Codex CLI 亲和性',
                'description' => 'Codex CLI 基于 prompt_cache_key 的渠道亲和性，确保连续对话路由到同一渠道',
                'model_patterns' => '/^gpt-.*$/',
                'path_patterns' => null,
                'user_agent_patterns' => null,
                'key_sources' => [
                    ['type' => 'json_path', 'path' => 'prompt_cache_key'],
                ],
                'key_combine_strategy' => 'first',
                'ttl_seconds' => 3600,
                'param_override_template' => null,
                'skip_retry_on_failure' => false,
                'include_group_in_key' => true,
                'is_enabled' => true,
                'priority' => 110,
            ],
            [
                'name' => 'Claude CLI 亲和性',
                'description' => 'Claude CLI 基于 metadata.user_id 的渠道亲和性，确保用户连续对话路由到同一渠道',
                'model_patterns' => '/^claude-.*$/',
                'path_patterns' => null,
                'user_agent_patterns' => null,
                'key_sources' => [
                    ['type' => 'json_path', 'path' => 'metadata.user_id'],
                ],
                'key_combine_strategy' => 'first',
                'ttl_seconds' => 3600,
                'param_override_template' => null,
                'skip_retry_on_failure' => false,
                'include_group_in_key' => true,
                'is_enabled' => true,
                'priority' => 105,
            ],
            [
                'name' => 'RooCode 亲和性',
                'description' => 'RooCode VS Code 扩展基于 API Key + User-Agent 组合的渠道亲和性，确保同一用户的连续对话路由到同一渠道',
                'model_patterns' => '/.*/',
                'path_patterns' => null,
                'user_agent_patterns' => ['RooCode'],
                'key_sources' => [
                    ['type' => 'api_key'],
                    ['type' => 'user_agent'],
                ],
                'key_combine_strategy' => 'json',
                'ttl_seconds' => 1200,
                'param_override_template' => null,
                'skip_retry_on_failure' => false,
                'include_group_in_key' => true,
                'is_enabled' => true,
                'priority' => 100,
            ],
            [
                'name' => 'Claude glm',
                'description' => 'GLM 模型基于 metadata.user_id 的渠道亲和性，确保用户连续对话路由到同一渠道',
                'model_patterns' => '/^glm-.*$/',
                'path_patterns' => null,
                'user_agent_patterns' => [],
                'key_sources' => [
                    ['type' => 'json_path', 'path' => 'metadata.user_id'],
                ],
                'key_combine_strategy' => 'concat',
                'ttl_seconds' => 2400,
                'param_override_template' => null,
                'skip_retry_on_failure' => false,
                'include_group_in_key' => true,
                'is_enabled' => true,
                'priority' => 105,
            ],
            [
                'name' => 'Anthropic Messages 亲和性',
                'description' => 'Anthropic Messages API 基于 metadata.user_id 的渠道亲和性',
                'model_patterns' => '/.*/',
                'path_patterns' => 'api/anthropic/v1/messages',
                'user_agent_patterns' => null,
                'key_sources' => [
                    ['type' => 'json_path', 'path' => 'metadata.user_id'],
                ],
                'key_combine_strategy' => 'json',
                'ttl_seconds' => 3600,
                'param_override_template' => null,
                'skip_retry_on_failure' => false,
                'include_group_in_key' => true,
                'is_enabled' => true,
                'priority' => 105,
            ],
        ];

        foreach ($rules as $rule) {
            ChannelAffinityRule::updateOrCreate(
                ['name' => $rule['name']],
                $rule
            );
        }

        $this->command->info('渠道亲和性规则已同步: '.count($rules).' 条');
    }
}
