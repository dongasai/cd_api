<?php

namespace App\Services\ChannelAffinity;

use App\Models\ChannelAffinityRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RuleMatcher
{
    protected ?array $cachedRules = null;

    protected int $cacheTtl = 60;

    public function match(Request $request, string $model): ?ChannelAffinityRule
    {
        $rules = $this->getEnabledRules();

        foreach ($rules as $rule) {
            if ($this->matchesRule($request, $model, $rule)) {
                return $rule;
            }
        }

        return null;
    }

    protected function getEnabledRules(): array
    {
        if ($this->cachedRules !== null) {
            return $this->cachedRules;
        }

        $cacheKey = 'channel_affinity_rules:enabled';

        $this->cachedRules = Cache::remember($cacheKey, $this->cacheTtl, function () {
            return ChannelAffinityRule::enabled()
                ->byPriority()
                ->get()
                ->all();
        });

        return $this->cachedRules;
    }

    protected function matchesRule(Request $request, string $model, ChannelAffinityRule $rule): bool
    {
        if (! $this->matchModel($model, $rule->model_patterns)) {
            return false;
        }

        if (! $this->matchPath($request->path(), $rule->path_patterns)) {
            return false;
        }

        if (! $this->matchUserAgent($request->userAgent(), $rule->user_agent_patterns ?? [])) {
            return false;
        }

        return true;
    }

    protected function matchModel(string $model, ?string $pattern): bool
    {
        if (empty($pattern)) {
            return true;
        }

        return (bool) preg_match($pattern, $model);
    }

    protected function matchPath(string $path, ?string $pattern): bool
    {
        if (empty($pattern)) {
            return true;
        }

        return $path === $pattern;
    }

    protected function matchUserAgent(?string $userAgent, array $patterns): bool
    {
        if (empty($patterns)) {
            return true;
        }

        if ($userAgent === null) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function clearCache(): void
    {
        $this->cachedRules = null;
        Cache::forget('channel_affinity_rules:enabled');
    }
}
