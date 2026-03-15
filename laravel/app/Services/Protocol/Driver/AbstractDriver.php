<?php

namespace App\Services\Protocol\Driver;

use App\Services\Protocol\Exceptions\ProtocolException;
use Illuminate\Support\Facades\Log;

/**
 * 抽象协议驱动基类
 */
abstract class AbstractDriver implements DriverInterface
{
    /**
     * 协议配置
     */
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * 默认实现：协议不同则需要转换
     */
    public function needsConversion(string $targetProtocol): bool
    {
        return $this->getProtocolName() !== $targetProtocol;
    }

    /**
     * 默认验证实现
     */
    public function validateRequest(array $rawRequest): bool
    {
        try {
            $this->parseRequest($rawRequest);

            return true;
        } catch (ProtocolException $e) {
            return false;
        }
    }

    /**
     * 获取配置项
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 设置配置
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * 默认错误响应
     */
    public function buildErrorResponse(string $message, string $type = 'error', int $code = 500): array
    {
        return [
            'error' => [
                'message' => $message,
                'type' => $type,
                'code' => $code,
            ],
        ];
    }

    /**
     * 解析 SSE 事件数据
     */
    protected function parseSSEEvent(string $rawEvent): ?array
    {
        Log::debug('parseSSEEvent', $rawEvent);
        $lines = explode("\n", trim($rawEvent));
        $event = [];
        $data = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, 'event:')) {
                $event['event'] = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data .= trim(substr($line, 5));
            }
        }

        if (empty($data)) {
            return null;
        }

        $event['data'] = $data;

        return $event;
    }

    /**
     * 构建 SSE 事件格式
     */
    protected function buildSSEEvent(?string $eventType, string $data): string
    {
        $output = '';
        if ($eventType !== null) {
            $output .= "event: {$eventType}\n";
        }
        $output .= "data: {$data}\n\n";

        return $output;
    }

    /**
     * 安全解析 JSON
     */
    protected function safeJsonDecode(string $json, ?array $default = null): ?array
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $default;
        }
    }

    /**
     * 安全编码 JSON
     */
    protected function safeJsonEncode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
