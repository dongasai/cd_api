<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 渠道亲和性规则预置数据填充
     */
    public function up(): void
    {
        $now = now();

        $rules = [
            [
                'name' => 'Codex CLI 亲和性',
                'description' => 'Codex CLI 基于 prompt_cache_key 的渠道亲和性，确保连续对话路由到同一渠道',
                'model_patterns' => '/^gpt-.*$/',
                'path_patterns' => null,
                'user_agent_patterns' => null,
                'key_sources' => json_encode([
                    ['type' => 'json_path', 'path' => 'prompt_cache_key'],
                ]),
                'key_combine_strategy' => 'first',
                'ttl_seconds' => 3600,
                'param_override_template' => null,
                'skip_retry_on_failure' => 0,
                'include_group_in_key' => 1,
                'is_enabled' => 1,
                'priority' => 110,
                'hit_count' => 0,
                'last_hit_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Claude CLI 亲和性',
                'description' => 'Claude CLI 基于 metadata.user_id 的渠道亲和性，确保用户连续对话路由到同一渠道',
                'model_patterns' => '/^claude-.*$/',
                'path_patterns' => null,
                'user_agent_patterns' => null,
                'key_sources' => json_encode([
                    ['type' => 'json_path', 'path' => 'metadata.user_id'],
                ]),
                'key_combine_strategy' => 'first',
                'ttl_seconds' => 3600,
                'param_override_template' => null,
                'skip_retry_on_failure' => 0,
                'include_group_in_key' => 1,
                'is_enabled' => 1,
                'priority' => 105,
                'hit_count' => 0,
                'last_hit_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'RooCode 亲和性',
                'description' => 'RooCode VS Code 扩展基于 API Key + User-Agent 组合的渠道亲和性，确保同一用户的连续对话路由到同一渠道',
                'model_patterns' => '/.*/',
                'path_patterns' => null,
                'user_agent_patterns' => json_encode(['RooCode']),
                'key_sources' => json_encode([
                    ['type' => 'api_key'],
                    ['type' => 'user_agent'],
                ]),
                'key_combine_strategy' => 'json',
                'ttl_seconds' => 1200,
                'param_override_template' => null,
                'skip_retry_on_failure' => 0,
                'include_group_in_key' => 1,
                'is_enabled' => 1,
                'priority' => 100,
                'hit_count' => 0,
                'last_hit_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Claude glm',
                'description' => 'GLM 模型基于 metadata.user_id 的渠道亲和性，确保用户连续对话路由到同一渠道',
                'model_patterns' => '/^glm-.*$/',
                'path_patterns' => null,
                'user_agent_patterns' => json_encode([]),
                'key_sources' => json_encode([
                    ['type' => 'json_path', 'path' => 'metadata.user_id'],
                ]),
                'key_combine_strategy' => 'concat',
                'ttl_seconds' => 2400,
                'param_override_template' => null,
                'skip_retry_on_failure' => 0,
                'include_group_in_key' => 1,
                'is_enabled' => 1,
                'priority' => 105,
                'hit_count' => 0,
                'last_hit_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Anthropic Messages 亲和性',
                'description' => 'Anthropic Messages API 基于 metadata.user_id 的渠道亲和性',
                'model_patterns' => '/.*/',
                'path_patterns' => 'api/anthropic/v1/messages',
                'user_agent_patterns' => null,
                'key_sources' => json_encode([
                    ['type' => 'json_path', 'path' => 'metadata.user_id'],
                ]),
                'key_combine_strategy' => 'json',
                'ttl_seconds' => 3600,
                'param_override_template' => null,
                'skip_retry_on_failure' => 0,
                'include_group_in_key' => 1,
                'is_enabled' => 1,
                'priority' => 105,
                'hit_count' => 0,
                'last_hit_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($rules as $rule) {
            DB::table('channel_affinity_rules')->updateOrInsert(
                ['name' => $rule['name']],
                $rule
            );
        }
    }

    public function down(): void
    {
        DB::table('channel_affinity_rules')
            ->whereIn('name', [
                'Codex CLI 亲和性',
                'Claude CLI 亲和性',
                'RooCode 亲和性',
                'Claude glm',
                'Anthropic Messages 亲和性',
            ])
            ->delete();
    }
};
