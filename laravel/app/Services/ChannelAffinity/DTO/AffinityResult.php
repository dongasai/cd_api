<?php

namespace App\Services\ChannelAffinity\DTO;

use App\Models\Channel;

readonly class AffinityResult
{
    public function __construct(
        public bool $isHit,
        public ?Channel $channel = null,
        public ?AffinityRule $rule = null,
        public ?string $keyHash = null,
        public ?string $keyHint = null,
        public bool $skipRetry = false,
        public ?array $paramOverride = null,
    ) {}

    public static function miss(): self
    {
        return new self(isHit: false);
    }

    public static function hit(
        Channel $channel,
        AffinityRule $rule,
        string $keyHash,
        string $keyHint,
        bool $skipRetry = false,
        ?array $paramOverride = null,
    ): self {
        return new self(
            isHit: true,
            channel: $channel,
            rule: $rule,
            keyHash: $keyHash,
            keyHint: $keyHint,
            skipRetry: $skipRetry,
            paramOverride: $paramOverride,
        );
    }

    public function toAuditData(): array
    {
        return [
            'rule_id' => $this->rule?->id,
            'key_hash' => $this->keyHash,
            'key_hint' => $this->keyHint,
            'is_affinity_hit' => $this->isHit,
            'skip_retry' => $this->skipRetry,
        ];
    }
}
