<?php

namespace App\Services\WebParser;

/**
 * HTML 内容预处理器
 *
 * 用于清理原始 HTML，移除无意义的标签和元素
 */
class ContentPreprocessor
{
    /**
     * 需要移除的标签列表
     *
     * @var array<int, string>
     */
    protected array $removeTags = [
        'script',
        'style',
        'nav',
        'footer',
        'aside',
        'header',
        'iframe',
        'noscript',
        'svg',
        'form',
        'button',
        'input',
    ];

    /**
     * 需要移除的 class 模式（广告、侧边栏等）
     *
     * @var array<int, string>
     */
    protected array $removeClassPatterns = [
        'ad',
        'ads',
        'advertisement',
        'banner',
        'sidebar',
        'widget',
        'popup',
        'modal',
        'cookie',
        'social',
        'share',
        'comment',
        'related',
        'recommendation',
        'promo',
        'sponsor',
    ];

    /**
     * 处理 HTML 内容
     *
     * @param  string  $html  原始 HTML
     * @return string 清理后的文本
     */
    public function process(string $html): string
    {
        // 构建标签移除正则（一次性匹配所有需要移除的标签）
        $tagsPattern = implode('|', $this->removeTags);
        $html = preg_replace('/<('.$tagsPattern.')[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<('.$tagsPattern.')[^>]*\/?>/is', '', $html);

        // 构建广告/侧边栏 class 移除正则（一次性匹配所有模式）
        $classPattern = implode('|', $this->removeClassPatterns);
        $html = preg_replace('/<[^>]*class=["\'][^"\']*('.$classPattern.')[^"\']*["\'][^>]*>.*?<\/[^>]+>/is', '', $html);

        // 移除隐藏元素
        $html = preg_replace('/<[^>]*style=["\'][^"\']*display:\s*none[^"\']*["\'][^>]*>.*?<\/[^>]+>/is', '', $html);

        // 移除 HTML 注释
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // 移除剩余 HTML 标签，保留内容
        $html = preg_replace('/<[^>]+>/', ' ', $html);

        // 清理多余空白
        $html = preg_replace('/\s+/', ' ', $html);

        return trim($html);
    }

    /**
     * 提取页面标题
     *
     * @param  string  $html  原始 HTML
     * @return string|null 页面标题
     */
    public function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }

        return null;
    }
}
