<?php

namespace App\Services\ChannelAffinity;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class KeyExtractor
{
    public function extract(Request $request, array $keySources, string $combineStrategy = 'first'): ?string
    {
        if (empty($keySources)) {
            return null;
        }

        $values = [];

        foreach ($keySources as $source) {
            $type = $source['type'] ?? null;
            $value = $this->extractValue($request, $type, $source);

            if ($value !== null) {
                $values[] = $value;
            }
        }

        if (empty($values)) {
            return null;
        }

        return $this->combineKeys($values, $combineStrategy);
    }

    protected function extractValue(Request $request, ?string $type, array $source): ?string
    {
        return match ($type) {
            'header' => $this->extractFromHeader($request, $source['key'] ?? ''),
            'json_path' => $this->extractFromJsonPath($request, $source['path'] ?? ''),
            'query' => $this->extractFromQuery($request, $source['key'] ?? ''),
            'api_key' => $this->extractApiKey($request),
            'client_ip' => $this->extractClientIp($request),
            'user_agent' => $this->extractUserAgent($request),
            default => null,
        };
    }

    protected function extractFromHeader(Request $request, string $key): ?string
    {
        if (empty($key)) {
            return null;
        }

        return $request->header($key);
    }

    protected function extractFromJsonPath(Request $request, string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $data = $request->all();

        return Arr::get($data, $path);
    }

    protected function extractFromQuery(Request $request, string $key): ?string
    {
        if (empty($key)) {
            return null;
        }

        return $request->query($key);
    }

    protected function extractApiKey(Request $request): ?string
    {
        $apiKey = $request->attributes->get('api_key');

        return $apiKey?->key;
    }

    protected function extractClientIp(Request $request): ?string
    {
        return $request->ip();
    }

    protected function extractUserAgent(Request $request): ?string
    {
        return $request->userAgent();
    }

    protected function combineKeys(array $values, string $strategy): string
    {
        $combined = match ($strategy) {
            'first' => $values[0] ?? '',
            'concat' => implode('|', $values),
            'hash' => implode('|', $values),
            default => $values[0] ?? '',
        };

        return $combined;
    }

    public function generateKeyHash(array $values, string $strategy): string
    {
        $combined = match ($strategy) {
            'first' => $values[0] ?? '',
            'concat' => implode('|', $values),
            'hash' => implode('|', $values),
            default => $values[0] ?? '',
        };

        return substr(md5($combined), 0, 16);
    }

    public function fingerprint(?string $key): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        $length = strlen($key);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($key, 0, 4).str_repeat('*', $length - 8).substr($key, -4);
    }
}
