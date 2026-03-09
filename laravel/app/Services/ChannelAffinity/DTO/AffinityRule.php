<?php

namespace App\Services\ChannelAffinity\DTO;

use App\Models\ChannelAffinityRule;

readonly class AffinityRule
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public ?array $modelPatterns,
        public ?array $pathPatterns,
        public ?array $userAgentPatterns,
        public ?array $keySources,
        public string $keyCombineStrategy,
        public int $ttlSeconds,
        public ?array $paramOverrideTemplate,
        public bool $skipRetryOnFailure,
        public bool $includeGroupInKey,
        public bool $isEnabled,
        public int $priority,
    ) {}

    public static function fromModel(ChannelAffinityRule $model): self
    {
        return new self(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            modelPatterns: $model->model_patterns,
            pathPatterns: $model->path_patterns,
            userAgentPatterns: $model->user_agent_patterns,
            keySources: $model->key_sources,
            keyCombineStrategy: $model->key_combine_strategy,
            ttlSeconds: $model->ttl_seconds,
            paramOverrideTemplate: $model->param_override_template,
            skipRetryOnFailure: $model->skip_retry_on_failure,
            includeGroupInKey: $model->include_group_in_key,
            isEnabled: $model->is_enabled,
            priority: $model->priority,
        );
    }
}
