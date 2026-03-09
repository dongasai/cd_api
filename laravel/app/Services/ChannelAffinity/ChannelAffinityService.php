<?php

namespace App\Services\ChannelAffinity;

use App\Models\Channel;
use App\Models\ChannelAffinityRule;
use App\Services\ChannelAffinity\DTO\AffinityResult;
use App\Services\ChannelAffinity\DTO\AffinityRule;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ChannelAffinityService
{
    protected ChannelAffinityCache $cache;

    protected RuleMatcher $ruleMatcher;

    protected KeyExtractor $keyExtractor;

    protected SettingService $settings;

    protected ?AffinityResult $lastResult = null;

    public function __construct(
        ChannelAffinityCache $cache,
        RuleMatcher $ruleMatcher,
        KeyExtractor $keyExtractor,
        SettingService $settings
    ) {
        $this->cache = $cache;
        $this->ruleMatcher = $ruleMatcher;
        $this->keyExtractor = $keyExtractor;
        $this->settings = $settings;
    }

    public function getPreferredChannel(Request $request, string $model, ?string $groupName = null): ?AffinityResult
    {
        if (! $this->isEnabled()) {
            $this->lastResult = AffinityResult::miss();

            return $this->lastResult;
        }

        $rule = $this->ruleMatcher->match($request, $model);

        if ($rule === null) {
            $this->lastResult = AffinityResult::miss();

            return $this->lastResult;
        }

        $keyHash = $this->extractKeyHash($request, $rule);

        if ($keyHash === null) {
            $this->lastResult = AffinityResult::miss();

            return $this->lastResult;
        }

        $cachedData = $this->cache->get($rule->id, $keyHash);

        if ($cachedData === null) {
            $this->lastResult = AffinityResult::miss();

            return $this->lastResult;
        }

        $channel = Channel::find($cachedData['channel_id']);

        if ($channel === null || ! $channel->isActive()) {
            $this->cache->forget($rule->id, $keyHash);
            $this->lastResult = AffinityResult::miss();

            return $this->lastResult;
        }

        $ruleDto = AffinityRule::fromModel($rule);
        $keyHint = $cachedData['key_hint'] ?? '';

        $this->lastResult = AffinityResult::hit(
            channel: $channel,
            rule: $ruleDto,
            keyHash: $keyHash,
            keyHint: $keyHint,
            skipRetry: $rule->skip_retry_on_failure,
            paramOverride: $rule->param_override_template,
        );

        $rule->recordHit();

        return $this->lastResult;
    }

    public function recordAffinity(Request $request, Channel $channel, string $model, ?string $groupName = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (! $this->shouldSwitchOnSuccess()) {
            return;
        }

        $rule = $this->ruleMatcher->match($request, $model);

        if ($rule === null) {
            return;
        }

        $keyHash = $this->extractKeyHash($request, $rule);

        if ($keyHash === null) {
            return;
        }

        $keyHint = $this->extractKeyHint($request, $rule);

        $cacheData = $this->cache->buildCacheData($channel, $rule->id, $keyHint);

        $this->cache->put($rule->id, $keyHash, $cacheData, $rule->ttl_seconds);

        Log::debug('Channel affinity recorded', [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'channel_id' => $channel->id,
            'key_hash' => $keyHash,
            'ttl' => $rule->ttl_seconds,
        ]);
    }

    public function shouldSkipRetry(Request $request): bool
    {
        return $this->lastResult?->skipRetry ?? false;
    }

    public function getParamOverrideTemplate(Request $request): ?array
    {
        return $this->lastResult?->paramOverride ?? null;
    }

    public function getAffinityInfo(Request $request): array
    {
        return $this->lastResult?->toAuditData() ?? [
            'rule_id' => null,
            'key_hash' => null,
            'key_hint' => null,
            'is_affinity_hit' => false,
            'skip_retry' => false,
        ];
    }

    public function getCacheStats(): array
    {
        return $this->cache->getStats();
    }

    public function clearCache(?int $ruleId = null): int
    {
        if ($ruleId !== null) {
            return $this->cache->forgetByRule($ruleId);
        }

        return $this->cache->forgetAll();
    }

    public function clearRuleCache(): void
    {
        $this->ruleMatcher->clearCache();
    }

    protected function extractKeyHash(Request $request, ChannelAffinityRule $rule): ?string
    {
        $keySources = $rule->key_sources ?? [];

        if (empty($keySources)) {
            return null;
        }

        $values = $this->extractKeyValues($request, $keySources);

        if (empty($values)) {
            return null;
        }

        return $this->keyExtractor->generateKeyHash($values, $rule->key_combine_strategy);
    }

    protected function extractKeyValues(Request $request, array $keySources): array
    {
        $values = [];

        foreach ($keySources as $source) {
            $type = $source['type'] ?? null;
            $value = match ($type) {
                'header' => $request->header($source['key'] ?? ''),
                'json_path' => Arr::get($request->all(), $source['path'] ?? ''),
                'query' => $request->query($source['key'] ?? ''),
                'api_key' => $request->attributes->get('api_key')?->key,
                'client_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                default => null,
            };

            if ($value !== null && $value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    protected function extractKeyHint(Request $request, ChannelAffinityRule $rule): string
    {
        $keySources = $rule->key_sources ?? [];

        if (empty($keySources)) {
            return '';
        }

        $firstSource = $keySources[0] ?? null;
        $type = $firstSource['type'] ?? null;

        $rawValue = match ($type) {
            'header' => $request->header($firstSource['key'] ?? ''),
            'json_path' => Arr::get($request->all(), $firstSource['path'] ?? ''),
            'query' => $request->query($firstSource['key'] ?? ''),
            'api_key' => $request->attributes->get('api_key')?->key,
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            default => null,
        };

        return $this->keyExtractor->fingerprint($rawValue);
    }

    protected function isEnabled(): bool
    {
        return (bool) $this->settings->get('channel_affinity.enabled', false);
    }

    protected function shouldSwitchOnSuccess(): bool
    {
        return (bool) $this->settings->get('channel_affinity.switch_on_success', true);
    }
}
