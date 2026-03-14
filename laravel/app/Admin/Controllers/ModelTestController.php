<?php

namespace App\Admin\Controllers;

use App\Models\Channel;
use App\Models\ModelTestLog;
use App\Models\PresetPrompt;
use App\Services\ModelTestService;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Illuminate\Http\Request;

/**
 * 模型测试控制器
 */
class ModelTestController extends AdminController
{
    /**
     * 页面标题
     */
    protected $title = '模型测试';

    protected ModelTestService $testService;

    public function __construct(ModelTestService $testService)
    {
        $this->testService = $testService;
    }

    /**
     * 测试页面
     */
    public function index(Content $content)
    {
        return $content
            ->header($this->title)
            ->description('AI模型对话测试')
            ->body(function (Row $row) {
                // 测试表单卡片
                $row->column(6, $this->buildTestFormCard());

                // 测试结果展示卡片
                $row->column(6, $this->buildResultCard());
            });
    }

    /**
     * 构建测试表单卡片
     */
    protected function buildTestFormCard(): Card
    {
        $channels = Channel::where('status', 'active')->pluck('name', 'id')->toArray();
        $presetPrompts = PresetPrompt::where('is_enabled', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->toArray();

        $testTypes = [
            ModelTestLog::TEST_TYPE_CHANNEL_DIRECT => '渠道直接测试',
            ModelTestLog::TEST_TYPE_SYSTEM_API => '系统API测试',
        ];

        $form = view('admin.model-test.form', [
            'channels' => $channels,
            'presetPrompts' => $presetPrompts,
            'testTypes' => $testTypes,
        ])->render();

        return Card::make('测试配置', $form);
    }

    /**
     * 构建结果展示卡片
     */
    protected function buildResultCard(): Card
    {
        $result = view('admin.model-test.result')->render();

        return Card::make('测试结果', $result)->id('test-result-card');
    }

    /**
     * 执行测试
     */
    public function test(Request $request)
    {
        $request->validate([
            'test_type' => 'required|in:channel_direct,system_api',
            'model' => 'required|string|max:100',
            'user_message' => 'nullable|string',
            'preset_prompt_id' => 'nullable|exists:preset_prompts,id',
            'is_stream' => 'boolean',
            'channel_id' => 'required_if:test_type,channel_direct|exists:channels,id',
        ]);

        $testType = $request->input('test_type');
        $model = $request->input('model');
        $userMessage = $request->input('user_message');
        $presetPromptId = $request->input('preset_prompt_id');
        $isStream = $request->boolean('is_stream');
        $channelId = $request->input('channel_id');

        $presetPrompt = $presetPromptId ? PresetPrompt::find($presetPromptId) : null;

        try {
            if ($testType === ModelTestLog::TEST_TYPE_CHANNEL_DIRECT) {
                $channel = Channel::findOrFail($channelId);

                // 流式测试返回 SSE 响应
                if ($isStream && $request->wantsJson() === false) {
                    return response()->stream(function () use ($channel, $model, $userMessage, $presetPrompt) {
                        echo 'data: '.json_encode(['status' => 'start'])."\n\n";

                        try {
                            $generator = $this->testService->testChannelDirectStream(
                                $channel,
                                $model,
                                $userMessage,
                                $presetPrompt
                            );

                            foreach ($generator as $chunk) {
                                echo 'data: '.json_encode([
                                    'content' => $chunk->content ?? '',
                                    'status' => 'streaming',
                                ])."\n\n";
                                ob_flush();
                                flush();
                            }

                            echo 'data: '.json_encode(['status' => 'done'])."\n\n";
                        } catch (\Exception $e) {
                            echo 'data: '.json_encode([
                                'status' => 'error',
                                'message' => $e->getMessage(),
                            ])."\n\n";
                        }
                    }, 200, [
                        'Content-Type' => 'text/event-stream',
                        'Cache-Control' => 'no-cache',
                        'X-Accel-Buffering' => 'no',
                    ]);
                }

                // 非流式测试
                $log = $this->testService->testChannelDirect(
                    $channel,
                    $model,
                    $userMessage,
                    $presetPrompt,
                    $isStream
                );
            } else {
                // 系统API测试
                $log = $this->testService->testSystemApi(
                    $model,
                    $userMessage,
                    $presetPrompt,
                    $isStream
                );
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $log->id,
                    'status' => $log->status,
                    'response_time_ms' => $log->response_time_ms,
                    'first_token_ms' => $log->first_token_ms,
                    'prompt_tokens' => $log->prompt_tokens,
                    'completion_tokens' => $log->completion_tokens,
                    'total_tokens' => $log->total_tokens,
                    'assistant_response' => $log->assistant_response,
                    'error_message' => $log->error_message,
                    'actual_model' => $log->actual_model,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取渠道支持的模型列表
     */
    public function getChannelModels(Request $request, $channelId)
    {
        $channel = Channel::findOrFail($channelId);
        $models = $channel->getModelsArray();

        return response()->json([
            'success' => true,
            'data' => $models,
        ]);
    }

    /**
     * 测试日志列表
     */
    protected function grid()
    {
        return Grid::make(ModelTestLog::with(['channel', 'presetPrompt']), function (Grid $grid) {
            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 列字段配置
            $grid->column('id', 'ID')->sortable();
            $grid->column('test_type', '测试类型')->display(function ($value) {
                $labels = [
                    ModelTestLog::TEST_TYPE_CHANNEL_DIRECT => '渠道直接测试',
                    ModelTestLog::TEST_TYPE_SYSTEM_API => '系统API测试',
                ];

                return $labels[$value] ?? $value;
            })->label([
                ModelTestLog::TEST_TYPE_CHANNEL_DIRECT => 'info',
                ModelTestLog::TEST_TYPE_SYSTEM_API => 'warning',
            ]);
            $grid->column('channel_name', '渠道名称')->display(function ($value) {
                return $value ?: '-';
            });
            $grid->column('model', '请求模型');
            $grid->column('actual_model', '实际模型')->display(function ($value) {
                return $value ?: '-';
            });
            $grid->column('status', '状态')->display(function ($value) {
                $labels = ModelTestLog::getStatuses();

                return $labels[$value] ?? $value;
            })->label([
                ModelTestLog::STATUS_SUCCESS => 'success',
                ModelTestLog::STATUS_FAILED => 'danger',
                ModelTestLog::STATUS_TIMEOUT => 'warning',
            ]);
            $grid->column('response_time_ms', '响应时间(ms)')->display(function ($value) {
                return $value ? number_format($value) : '-';
            })->sortable();
            $grid->column('first_token_ms', '首token(ms)')->display(function ($value) {
                return $value ? number_format($value) : '-';
            });
            $grid->column('total_tokens', '总Token')->display(function ($value) {
                return $value ? number_format($value) : '-';
            });
            $grid->column('is_stream', '流式')->bool();
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                $filter->panel();
                $filter->expand(true);

                $filter->equal('id', 'ID');
                $filter->equal('test_type', '测试类型')->select([
                    ModelTestLog::TEST_TYPE_CHANNEL_DIRECT => '渠道直接测试',
                    ModelTestLog::TEST_TYPE_SYSTEM_API => '系统API测试',
                ]);
                $filter->equal('channel_id', '渠道ID');
                $filter->like('model', '模型');
                $filter->equal('status', '状态')->select(ModelTestLog::getStatuses());
                $filter->equal('is_stream', '流式')->select([
                    1 => '是',
                    0 => '否',
                ]);
            });

            // 快捷搜索
            $grid->quickSearch(['id', 'model', 'channel_name']);

            // 禁用新增和编辑按钮
            $grid->disableCreateButton();
            $grid->disableEditButton();
            $grid->disableViewButton();

            // 启用导出
            $grid->export();

            // 行详情
            $grid->showColumnSelector();
        });
    }
}
