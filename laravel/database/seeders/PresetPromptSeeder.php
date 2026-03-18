<?php

namespace Database\Seeders;

use App\Models\PresetPrompt;
use Illuminate\Database\Seeder;

/**
 * 预设提示词数据填充
 */
class PresetPromptSeeder extends Seeder
{
    /**
     * 运行数据填充
     */
    public function run(): void
    {
        $prompts = [
            // 通用类
            [
                'name' => '自我介绍',
                'category' => 'general',
                'content' => '你好，请介绍一下你自己，包括你的能力、特点和使用场景。',
                'variables' => null,
                'headers' => null,
                'is_enabled' => true,
                'sort_order' => 1,
            ],
            [
                'name' => '详细解释',
                'category' => 'general',
                'content' => '请详细解释以下内容：{topic}。要求：\n1. 概念清晰\n2. 举例说明\n3. 应用场景',
                'variables' => ['topic' => '要解释的主题'],
                'headers' => null,
                'is_enabled' => true,
                'sort_order' => 2,
            ],

            // 编程类
            [
                'name' => '代码审查',
                'category' => 'programming',
                'content' => '请审查以下代码并提供改进建议：\n\n```\n{code}\n```\n\n请从以下方面进行分析：\n1. 代码质量\n2. 性能优化\n3. 安全性\n4. 最佳实践',
                'variables' => ['code' => '要审查的代码'],
                'headers' => null,
                'is_enabled' => true,
                'sort_order' => 10,
            ],
            [
                'name' => '代码解释',
                'category' => 'programming',
                'content' => '请解释以下代码的功能和实现原理：\n\n```\n{code}\n```\n\n要求：\n1. 逐行解释\n2. 说明设计思路\n3. 指出关键点',
                'variables' => ['code' => '要解释的代码'],
                'headers' => null,
                'is_enabled' => true,
                'sort_order' => 11,
            ],
            [
                'name' => 'Bug调试助手',
                'category' => 'programming',
                'content' => '我遇到了以下问题：\n\n{problem}\n\n错误信息：\n{error}\n\n请帮我分析原因并提供解决方案。',
                'variables' => [
                    'problem' => '问题描述',
                    'error' => '错误信息',
                ],
                'headers' => null,
                'is_enabled' => true,
                'sort_order' => 12,
            ],

            // 翻译类
            [
                'name' => '中英翻译',
                'category' => 'translation',
                'content' => '请将以下内容翻译成{target_language}：\n\n{text}\n\n要求：\n1. 准确表达原意\n2. 符合目标语言习惯\n3. 保持专业术语准确',
                'variables' => [
                    'target_language' => '目标语言',
                    'text' => '要翻译的文本',
                ],
                'headers' => null,
                'is_enabled' => true,
                'sort_order' => 20,
            ],
            [
                'name' => '专业术语翻译',
                'category' => 'translation',
                'content' => '请翻译以下专业术语：{term}\n\n领域：{field}\n\n提供：\n1. 标准翻译\n2. 常见替代翻译\n3. 使用示例',
                'variables' => [
                    'term' => '专业术语',
                    'field' => '所属领域',
                ],
                'headers' => null,
                'is_enabled' => true,
                'sort_order' => 21,
            ],

            // 分析类
            [
                'name' => '问题分析',
                'category' => 'analysis',
                'content' => '请分析以下问题：{problem}\n\n从以下维度进行分析：\n1. 问题背景\n2. 关键因素\n3. 潜在风险\n4. 解决思路',
                'variables' => ['problem' => '要分析的问题'],
                'headers' => null,
                'is_enabled' => true,
                'sort_order' => 30,
            ],
            [
                'name' => '数据解读',
                'category' => 'analysis',
                'content' => '请解读以下数据：\n\n{data}\n\n分析要点：\n1. 数据特征\n2. 趋势分析\n3. 异常点识别\n4. 建议措施',
                'variables' => ['data' => '要分析的数据'],
                'headers' => null,
                'is_enabled' => true,
                'sort_order' => 31,
            ],

            // 写作类
            [
                'name' => '文章润色',
                'category' => 'writing',
                'content' => '请润色以下文章：\n\n{article}\n\n润色要求：\n1. 改善语言表达\n2. 优化文章结构\n3. 提升可读性\n4. 保持原意',
                'variables' => ['article' => '要润色的文章'],
                'headers' => null,
                'is_enabled' => true,
                'sort_order' => 40,
            ],
            [
                'name' => '大纲生成',
                'category' => 'writing',
                'content' => '请为以下主题生成文章大纲：\n\n主题：{topic}\n字数要求：{word_count}字\n\n大纲要求：\n1. 结构清晰\n2. 逻辑合理\n3. 重点突出\n4. 层次分明',
                'variables' => [
                    'topic' => '文章主题',
                    'word_count' => '字数要求',
                ],
                'headers' => null,
                'is_enabled' => true,
                'sort_order' => 41,
            ],

            // 其他类
            [
                'name' => '头脑风暴',
                'category' => 'other',
                'content' => '请围绕"{topic}"进行头脑风暴，提供10个创新的想法或建议。\n\n要求：\n1. 想法新颖\n2. 切实可行\n3. 有创意性\n4. 多角度思考',
                'variables' => ['topic' => '头脑风暴主题'],
                'headers' => null,
                'is_enabled' => true,
                'sort_order' => 50,
            ],
            [
                'name' => '学习计划',
                'category' => 'other',
                'content' => '我想学习：{subject}\n\n当前水平：{level}\n学习时间：每天{hours}小时\n目标：{goal}\n\n请为我制定一个详细的学习计划。',
                'variables' => [
                    'subject' => '学习科目',
                    'level' => '当前水平',
                    'hours' => '可用时间',
                    'goal' => '学习目标',
                ],
                'headers' => null,
                'is_enabled' => true,
                'sort_order' => 51,
            ],
        ];

        foreach ($prompts as $prompt) {
            PresetPrompt::create($prompt);
        }

        $this->command->info('预设提示词数据填充完成，共 '.count($prompts).' 条记录。');
    }
}
