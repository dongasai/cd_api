<?php

namespace App\Services\UserAgent;

use App\Models\AuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * User-Agent 分组服务
 *
 * 负责从审计日志中提取、分组和统计 User-Agent
 */
class UserAgentGroupingService
{
    /**
     * 分组规则配置
     */
    protected array $groupRules = [
        'claude-cli' => ['claude-cli', 'claude_code'],
        'RooCode' => ['roocode', 'roo-code'],
        'curl' => ['curl'],
        'Chrome' => ['chrome'],
        'Firefox' => ['firefox'],
        'Safari' => ['safari'],
        'Python' => ['python-requests', 'python'],
        'Node.js' => ['node', 'axios'],
        'Go' => ['go-http'],
        'Java' => ['java'],
        'OpenAI-SDK' => ['openai'],
        'Anthropic-SDK' => ['anthropic'],
    ];

    /**
     * 对单个 User-Agent 进行分组
     */
    public function groupUserAgent(string $userAgent): string
    {
        $userAgentLower = strtolower($userAgent);

        // 按优先级匹配分组规则
        foreach ($this->groupRules as $groupName => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($userAgentLower, $keyword) !== false) {
                    return $groupName;
                }
            }
        }

        // Safari 特殊处理：避免被 Chrome 匹配
        if (strpos($userAgentLower, 'safari') !== false && strpos($userAgentLower, 'chrome') === false) {
            return 'Safari';
        }

        // 未识别：提取第一个 / 前的部分
        $parts = explode('/', $userAgent);
        $firstPart = trim($parts[0]);

        if (strlen($firstPart) > 30) {
            return 'Other ('.substr($firstPart, 0, 30).'...)';
        }

        return $firstPart ?: 'Unknown';
    }

    /**
     * 批量分组 User-Agent
     *
     * @param  array|Collection  $userAgents
     */
    public function batchGroup($userAgents): array
    {
        $result = [];
        foreach ($userAgents as $userAgent) {
            $group = $this->groupUserAgent($userAgent);
            $result[$userAgent] = $group;
        }

        return $result;
    }

    /**
     * 从审计日志中提取并统计 User-Agent 分组数据
     */
    public function getGroupedStatsFromAuditLogs(
        ?int $channelId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        int $limit = 10
    ): Collection {
        // 构建查询
        $query = AuditLog::whereNotNull('user_agent')
            ->select(
                'user_agent',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('SUM(cost) as total_cost'),
                DB::raw('AVG(latency_ms) as avg_latency'),
                DB::raw('AVG(first_token_ms) as avg_first_token')
            )
            ->groupBy('user_agent');

        // 应用筛选条件
        if ($channelId) {
            $query->where('channel_id', $channelId);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $userAgents = $query->get();

        // 按分组聚合数据
        $groupedStats = [];
        foreach ($userAgents as $item) {
            $group = $this->groupUserAgent($item->user_agent);

            if (! isset($groupedStats[$group])) {
                $groupedStats[$group] = [
                    'group' => $group,
                    'request_count' => 0,
                    'total_tokens' => 0,
                    'total_cost' => 0,
                    'avg_latency_samples' => [],
                    'avg_first_token_samples' => [],
                    'user_agents' => [],
                ];
            }

            $groupedStats[$group]['request_count'] += $item->request_count;
            $groupedStats[$group]['total_tokens'] += $item->total_tokens;
            $groupedStats[$group]['total_cost'] += $item->total_cost;
            $groupedStats[$group]['avg_latency_samples'][] = $item->avg_latency;
            $groupedStats[$group]['avg_first_token_samples'][] = $item->avg_first_token;
            $groupedStats[$group]['user_agents'][] = [
                'user_agent' => $item->user_agent,
                'request_count' => $item->request_count,
            ];
        }

        // 计算平均延迟
        foreach ($groupedStats as &$stats) {
            $latencies = array_filter($stats['avg_latency_samples'], fn ($v) => $v > 0);
            $firstTokens = array_filter($stats['avg_first_token_samples'], fn ($v) => $v > 0);

            $stats['avg_latency'] = count($latencies) > 0
                ? round(array_sum($latencies) / count($latencies), 0)
                : 0;

            $stats['avg_first_token'] = count($firstTokens) > 0
                ? round(array_sum($firstTokens) / count($firstTokens), 0)
                : 0;

            $stats['version_count'] = count($stats['user_agents']);

            // 清理临时数据
            unset($stats['avg_latency_samples'], $stats['avg_first_token_samples']);
        }

        // 按请求数排序并限制数量
        $collection = collect(array_values($groupedStats))
            ->sortByDesc('request_count')
            ->take($limit)
            ->values();

        return $collection;
    }

    /**
     * 获取所有已知的 User-Agent 分组
     */
    public function getAllKnownGroups(): array
    {
        return array_keys($this->groupRules);
    }

    /**
     * 添加自定义分组规则
     */
    public function addGroupRule(string $groupName, array $keywords): void
    {
        $this->groupRules[$groupName] = $keywords;
    }

    /**
     * 获取分组规则配置
     */
    public function getGroupRules(): array
    {
        return $this->groupRules;
    }
}
