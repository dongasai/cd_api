<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * 获取全球LLM模型排行榜
 *
 * 从 benchlm.ai 获取排行数据，从本地 models.dev 缓存补充 context_length/pricing
 * php artisan cdapi:stats:model-ranking --top=100 --by=coding --save
 */
class ModelRanking extends Command
{
    protected $signature = 'cdapi:stats:model-ranking
                            {--top=100 : 排行数量}
                            {--by=coding : 排序字段: score/coding/agentic/knowledge/math/multimodal}
                            {--format=table : 输出格式: table/json/csv}
                            {--save : 保存数据到文件}';

    protected $description = '获取全球LLM模型排行榜（真实排行数据）';

    /** models.dev 本地缓存索引 [model_slug => model_data] */
    private array $modelsDevIndex = [];

    public function handle(): int
    {
        $top = (int) $this->option('top');
        $sortBy = $this->option('by');
        $format = $this->option('format');
        $save = $this->option('save');

        $validSortFields = ['score', 'coding', 'agentic', 'knowledge', 'math', 'multimodal'];
        if (! in_array($sortBy, $validSortFields)) {
            $this->error('排序字段无效，可选: '.implode(', ', $validSortFields));

            return self::FAILURE;
        }

        if (! in_array($format, ['table', 'json', 'csv'])) {
            $this->error('输出格式无效，可选: table, json, csv');

            return self::FAILURE;
        }

        // 加载本地 models.dev 缓存
        $this->loadModelsDevIndex();

        $this->info('正在从 benchlm.ai 获取排行榜数据...');

        $data = $this->fetchBenchlmData();
        if ($data === null) {
            return self::FAILURE;
        }

        $models = $this->processData($data, $sortBy, $top);

        if ($models->isEmpty()) {
            $this->warn('未获取到排行数据');

            return self::SUCCESS;
        }

        if ($save) {
            $this->saveDataToFile($models);
        }

        $this->outputResult($models, $format, $data['lastUpdated'] ?? '', $sortBy);

        return self::SUCCESS;
    }

    /**
     * 加载本地 models.dev 缓存，构建索引
     * 优先从 database/data/models.json，降级到 storage/app/models_dev.json
     */
    private function loadModelsDevIndex(): void
    {
        $dataFile = base_path('database/data/models.json');
        $cacheFile = storage_path('app/models_dev.json');

        $source = null;
        if (file_exists($dataFile)) {
            $source = $dataFile;
        } elseif (file_exists($cacheFile)) {
            $source = $cacheFile;
        }

        if ($source === null) {
            $this->warn('models.dev 缓存不存在，请先运行 cdapi:stats:model-sync');

            return;
        }

        $data = json_decode(file_get_contents($source), true);
        $models = $data['data'] ?? [];

        // 构建索引: 同时按 "provider/model" 和 "model" 两种 key
        foreach ($models as $m) {
            $id = $m['id'] ?? '';
            if (! $id) {
                continue;
            }

            // 完整 id: "anthropic/claude-opus-5"
            $this->modelsDevIndex[$id] = $m;

            // 仅 model 部分: "claude-opus-5"
            if (str_contains($id, '/')) {
                $parts = explode('/', $id, 2);
                $this->modelsDevIndex[$parts[1]] = $m;
            }
        }

        $this->info('已加载 models.dev 缓存: '.count($this->modelsDevIndex).' 个模型');
    }

    /**
     * 从 models.dev 索引中查找模型数据
     */
    private function findInModelsDev(string $modelSlug, string $provider = ''): ?array
    {
        // 精确匹配
        if (isset($this->modelsDevIndex[$modelSlug])) {
            return $this->modelsDevIndex[$modelSlug];
        }

        // 按供应商前缀匹配: "claude-opus-5" + provider="anthropic" -> "anthropic/claude-opus-5"
        if ($provider) {
            $prefixed = $provider.'/'.$modelSlug;
            if (isset($this->modelsDevIndex[$prefixed])) {
                return $this->modelsDevIndex[$prefixed];
            }
        }

        // 模糊匹配
        foreach ($this->modelsDevIndex as $key => $m) {
            if (str_contains($key, $modelSlug) || str_contains($modelSlug, $key)) {
                return $m;
            }
        }

        return null;
    }

    /**
     * 从 benchlm.ai 获取排行榜数据
     */
    private function fetchBenchlmData(): ?array
    {
        try {
            $response = Http::timeout(30)
                ->get('https://benchlm.ai/api/data/leaderboard', [
                    'mode' => 'bench-align-v5',
                ]);

            if (! $response->successful()) {
                $this->error('请求失败: HTTP '.$response->status());

                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            $this->error('请求异常: '.$e->getMessage());

            return null;
        }
    }

    /**
     * 处理排行数据
     */
    private function processData(array $data, string $sortBy, int $top): Collection
    {
        $models = collect($data['models'] ?? []);

        $sortKey = match ($sortBy) {
            'score' => 'overallScore',
            'coding' => fn ($m) => $m['categoryScores']['coding'] ?? 0,
            'agentic' => fn ($m) => $m['categoryScores']['agentic'] ?? 0,
            'knowledge' => fn ($m) => $m['categoryScores']['knowledge'] ?? 0,
            'math' => fn ($m) => $m['categoryScores']['math'] ?? 0,
            'multimodal' => fn ($m) => $m['categoryScores']['multimodalGrounded'] ?? 0,
        };

        if ($sortBy === 'score') {
            $models = $models->sortByDesc('overallScore');
        } else {
            $models = $models->sortByDesc($sortKey);
        }

        return $models->take($top)->values();
    }

    /**
     * 输出结果
     */
    private function outputResult(Collection $models, string $format, string $lastUpdated, string $sortBy): void
    {
        $sortLabel = match ($sortBy) {
            'score' => '综合评分',
            'coding' => '编程能力',
            'agentic' => 'Agent能力',
            'knowledge' => '知识能力',
            'math' => '数学能力',
            'multimodal' => '多模态能力',
        };

        if ($format === 'json') {
            $this->line(json_encode($models, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return;
        }

        if ($format === 'csv') {
            $this->line('排名,模型,厂商,类型,综合评分,编程,Agent,知识,数学,多模态,输入价格,输出价格,证据状态');
            foreach ($models as $index => $m) {
                $cs = $m['categoryScores'] ?? [];
                $this->line(sprintf(
                    '%d,"%s","%s",%s,%s,%s,%s,%s,%s,%s,%s,%s,%s',
                    $index + 1,
                    $m['model'],
                    $m['creator'],
                    $m['sourceType'],
                    $m['overallScore'] ?? '',
                    $cs['coding'] ?? '',
                    $cs['agentic'] ?? '',
                    $cs['knowledge'] ?? '',
                    $cs['math'] ?? '',
                    $cs['multimodalGrounded'] ?? '',
                    $m['inputPrice'] ?? '',
                    $m['outputPrice'] ?? '',
                    $m['evidenceStatus'] ?? ''
                ));
            }

            return;
        }

        $this->info("排行依据: {$sortLabel} | 数据更新: {$lastUpdated}");

        $rows = [];
        foreach ($models as $index => $m) {
            $cs = $m['categoryScores'] ?? [];
            $rows[] = [
                $index + 1,
                $m['model'],
                $m['creator'],
                $m['sourceType'],
                $m['overallScore'] ?? '-',
                $cs['coding'] ?? '-',
                $cs['agentic'] ?? '-',
                $cs['knowledge'] ?? '-',
                $cs['math'] ?? '-',
                $cs['multimodalGrounded'] ?? '-',
                $this->formatPrice($m['inputPrice'] ?? null),
                $this->formatPrice($m['outputPrice'] ?? null),
                $m['evidenceStatus'] ?? '-',
            ];
        }

        $this->table(
            ['排名', '模型', '厂商', '类型', '综合', '编程', 'Agent', '知识', '数学', '多模态', '输入$/M', '输出$/M', '证据'],
            $rows
        );

        $this->comment("共 {$models->count()} 个模型 | 数据来源: benchlm.ai | 方法论: {$sortLabel}");
    }

    private function formatPrice(?float $price): string
    {
        if ($price === null) {
            return '-';
        }

        if ($price == 0) {
            return '免费';
        }

        return '$'.$price;
    }

    /**
     * 保存数据到文件
     */
    private function saveDataToFile(Collection $models): void
    {
        $dataPath = database_path('data/model_ranking.php');

        $data = [];
        foreach ($models as $m) {
            // 基础能力：从 benchlm.ai 评分推导
            $cs = $m['categoryScores'] ?? [];
            $capabilities = [];
            if (isset($cs['coding']) && $cs['coding'] !== null) {
                $capabilities[] = 'coding';
            }
            if (isset($cs['agentic']) && $cs['agentic'] !== null) {
                $capabilities[] = 'agentic';
            }
            if (isset($cs['reasoning']) && $cs['reasoning'] !== null) {
                $capabilities[] = 'reasoning';
            }
            if (isset($cs['knowledge']) && $cs['knowledge'] !== null) {
                $capabilities[] = 'knowledge';
            }
            if (isset($cs['multimodalGrounded']) && $cs['multimodalGrounded'] !== null) {
                $capabilities[] = 'multimodal';
            }
            if (isset($cs['multilingual']) && $cs['multilingual'] !== null) {
                $capabilities[] = 'multilingual';
            }
            if (isset($cs['math']) && $cs['math'] !== null) {
                $capabilities[] = 'math';
            }

            $slug = $this->modelNameToSlug($m['model']);
            $provider = $this->creatorToProvider($m['creator'] ?? '');
            $devModel = $this->findInModelsDev($slug, $provider);

            // 从 models.dev 补充 context_length、pricing、modality
            $contextLength = $devModel['context_length'] ?? null;
            $benchPrompt = $m['inputPrice'] ?? null;
            $benchCompletion = $m['outputPrice'] ?? null;
            $pricingPrompt = ($benchPrompt !== null && $benchPrompt > 0) ? $benchPrompt : null;
            $pricingCompletion = ($benchCompletion !== null && $benchCompletion > 0) ? $benchCompletion : null;
            $pricingCacheRead = null;
            $modality = null;
            $inputModalities = null;
            $outputModalities = null;
            $supportedParameters = null;

            if ($devModel !== null) {
                // 补充 pricing
                $devPricing = $devModel['pricing'] ?? [];
                if (isset($devPricing['prompt']) && $devPricing['prompt'] > 0 && $pricingPrompt === null) {
                    $pricingPrompt = round((float) $devPricing['prompt'] * 1000000, 3);
                }
                if (isset($devPricing['completion']) && $devPricing['completion'] > 0 && $pricingCompletion === null) {
                    $pricingCompletion = round((float) $devPricing['completion'] * 1000000, 3);
                }
                if (isset($devPricing['input_cache_read']) && $devPricing['input_cache_read'] > 0) {
                    $pricingCacheRead = round((float) $devPricing['input_cache_read'] * 1000000, 3);
                }

                // 补充 modality
                $arch = $devModel['architecture'] ?? [];
                $modality = $arch['modality'] ?? null;
                $inputModalities = $arch['input_modalities'] ?? null;
                $outputModalities = $arch['output_modalities'] ?? null;

                // 补充 supported_parameters
                $supportedParameters = $devModel['supported_parameters'] ?? null;
            }

            // models.dev 未收录的模型，降级使用已知映射
            if ($contextLength === null) {
                $contextLength = $this->getKnownContextLength();
            }

            // 构建 config: modality、input/output_modalities、supported_parameters
            $config = [];
            if ($modality !== null) {
                $config['modality'] = $modality;
            }
            if ($inputModalities !== null) {
                $config['input_modalities'] = $inputModalities;
            }
            if ($outputModalities !== null) {
                $config['output_modalities'] = $outputModalities;
            }
            if ($supportedParameters !== null) {
                $config['supported_parameters'] = $supportedParameters;
            }

            // hugging_face_id 从 models.dev 获取
            $huggingFaceId = $devModel['hugging_face_id'] ?? null;

            $data[] = [
                'model_name' => $slug,
                'display_name' => $m['model'],
                'provider' => $this->creatorToProvider($m['creator'] ?? ''),
                'hugging_face_id' => $huggingFaceId,
                'common_name' => $this->extractCommonName($m['model']),
                'context_length' => $contextLength,
                'pricing_prompt' => $pricingPrompt,
                'pricing_completion' => $pricingCompletion,
                'pricing_input_cache_read' => $pricingCacheRead,
                'capabilities' => $capabilities,
                'config' => $config ?: null,
                'overall_score' => $m['overallScore'] ?? null,
                'source_type' => $m['sourceType'] ?? 'Proprietary',
                'evidence_status' => $m['evidenceStatus'] ?? 'supported',
            ];
        }

        $content = "<?php\n\n/**\n * 全球LLM模型排行榜数据\n * 数据来源: benchlm.ai + models.dev\n * 更新时间: ".now()->toDateTimeString()."\n */\nreturn ".var_export($data, true).";\n";

        file_put_contents($dataPath, $content);
        $this->info("数据已保存到: {$dataPath}");
    }

    private function modelNameToSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = str_replace([' ', '(', ')'], ['-', '', ''], $slug);
        $slug = str_replace(['--'], ['-'], $slug);

        return $slug;
    }

    private function creatorToProvider(string $creator): string
    {
        return match ($creator) {
            'Anthropic' => 'anthropic',
            'OpenAI' => 'openai',
            'Google' => 'google',
            'xAI' => 'xai',
            'Moonshot AI' => 'moonshot',
            'Meta' => 'meta',
            'Alibaba' => 'alibaba',
            'Z.AI' => 'zhipu',
            'MiniMax' => 'minimax',
            'Tencent' => 'tencent',
            'Xiaomi' => 'xiaomi',
            'DeepSeek' => 'deepseek',
            'Thinking Machines Lab' => 'thinkingmachines',
            'InternScience' => 'internscience',
            default => strtolower(str_replace(' ', '', $creator)),
        };
    }

    private function extractCommonName(string $name): string
    {
        $parts = explode(' ', $name);
        if (count($parts) > 1) {
            return implode(' ', array_slice($parts, 1));
        }

        return $name;
    }

    /**
     * 已知模型 context_length 降级映射
     * models.dev 未收录的模型，默认 200000
     */
    private function getKnownContextLength(): ?int
    {
        return 200000;
    }
}
