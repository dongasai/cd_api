<?php

namespace App\Services\Router;

use App\Models\Channel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * User-Agent过滤服务
 *
 * 负责根据请求的User-Agent过滤渠道
 */
class UserAgentFilterService
{
    /**
     * 过滤不匹配User-Agent的渠道
     *
     * @param  Collection  $channels  候选渠道集合
     * @param  string  $userAgent  请求的User-Agent
     * @return Collection 过滤后的渠道集合
     */
    public function filterChannels(Collection $channels, string $userAgent): Collection
    {
        // 如果User-Agent为空，不过滤
        if (empty($userAgent)) {
            return $channels;
        }

        return $channels->filter(function (Channel $channel) use ($userAgent) {
            // 检查渠道是否允许该User-Agent
            $allowed = $channel->isUserAgentAllowed($userAgent);

            if (! $allowed) {
                Log::info('渠道User-Agent不匹配，已跳过', [
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->name,
                    'user_agent' => $userAgent,
                ]);
            }

            return $allowed;
        });
    }
}
