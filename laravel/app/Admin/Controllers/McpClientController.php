<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\TestMcpConnection;
use App\Models\McpClient;
use App\Services\McpClientService;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;
use Illuminate\Http\Request;

/**
 * MCP 客户端管理控制器
 */
class McpClientController extends AdminController
{
    /**
     * 语言包名称
     *
     * @var string
     */
    public $translation = 'admin-mcp-client';

    /**
     * 列表页面
     */
    protected function grid()
    {
        return Grid::make(McpClient::query(), function (Grid $grid) {
            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 列字段配置
            $grid->column('id')->sortable();
            $grid->column('name')->link(function () {
                return admin_url('mcp-clients/'.$this->id);
            });
            $grid->column('slug');
            $grid->column('transport')->display(function ($value) {
                $labels = McpClient::getTransports();

                return $labels[$value] ?? $value;
            });
            $grid->column('url')->limit(50);
            $grid->column('status')->display(function ($value) {
                $labels = McpClient::getStatuses();
                $colors = [
                    McpClient::STATUS_ACTIVE => 'success',
                    McpClient::STATUS_INACTIVE => 'secondary',
                    McpClient::STATUS_ERROR => 'danger',
                ];
                $color = $colors[$value] ?? 'secondary';
                $label = $labels[$value] ?? $value;

                return "<span class='badge bg-{$color}'>{$label}</span>";
            });
            $grid->column('last_connected_at')->sortable();
            $grid->column('created_at')->sortable();

            // 操作按钮
            $grid->actions([
                new TestMcpConnection,
            ]);

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                $filter->panel();
                $filter->like('name');
                $filter->equal('transport')->select(McpClient::getTransports());
                $filter->equal('status')->select(McpClient::getStatuses());
            });

            // 快速搜索
            $grid->quickSearch(['name', 'slug', 'url']);
        });
    }

    /**
     * 详情页面
     */
    protected function detail($id)
    {
        return Show::make($id, McpClient::query(), function (Show $show) {
            $show->field('id');
            $show->field('name');
            $show->field('slug');
            $show->field('transport')->as(function ($value) {
                return McpClient::getTransports()[$value] ?? $value;
            });
            $show->field('url');
            $show->field('command');
            $show->field('args')->json();
            $show->field('headers')->json();
            $show->field('timeout');
            $show->field('status')->as(function ($value) {
                $labels = McpClient::getStatuses();

                return $labels[$value] ?? $value;
            });
            $show->field('last_connected_at');
            $show->field('connection_error');
            $show->field('capabilities')->json();
            $show->field('description');
            $show->field('created_at');
            $show->field('updated_at');

            // 操作按钮
            $show->tools(function (Show\Tools $tools) {
                $tools->append(new TestMcpConnection);
            });
        });
    }

    /**
     * 表单页面
     */
    protected function form()
    {
        return Form::make(McpClient::query(), function (Form $form) {
            $form->display('id');

            $form->text('name')->required();
            $form->text('slug')->required()->help('唯一标识符，用于 API 调用');

            $form->select('transport')
                ->options(McpClient::getTransports())
                ->default('http')
                ->required();

            // HTTP+SSE 配置
            $form->tab('HTTP+SSE 配置', function (Form $form) {
                $form->text('url')
                    ->help('MCP Server 的 HTTP+SSE 地址，如 http://127.0.0.1:32126/mcp/cdapi');
                $form->keyValue('headers')
                    ->help('HTTP 请求头，如 Authorization: Bearer sk-xxx');
            });

            // Stdio 配置
            $form->tab('Stdio 配置', function (Form $form) {
                $form->text('command')
                    ->help('执行的命令，如 npx、php artisan');
                $form->textarea('args')
                    ->help('命令参数（JSON 数组格式）');
            });

            $form->number('timeout')
                ->min(5)
                ->max(300)
                ->default(30)
                ->help('连接超时秒数');

            $form->select('status')
                ->options(McpClient::getStatuses())
                ->default('inactive');

            $form->textarea('description')->rows(3);

            $form->display('created_at');
            $form->display('updated_at');

            // 保存后更新能力信息
            $form->saved(function (Form $form) {
                $client = $form->model();
                // 如果是 HTTP 且有 URL，自动测试连接
                if ($client->isHttp() && $client->url) {
                    try {
                        $service = app(McpClientService::class);
                        $service->testConnection($client);
                    } catch (\Exception $e) {
                        // 静默处理，用户可手动测试
                    }
                }
            });
        });
    }

    /**
     * 测试连接接口
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testConnectionApi(Request $request, $id)
    {
        $client = McpClient::findOrFail($id);
        $service = app(McpClientService::class);

        $result = $service->testConnection($client);

        return response()->json($result);
    }

    /**
     * 获取工具列表接口
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function listToolsApi(Request $request, $id)
    {
        $client = McpClient::findOrFail($id);
        $service = app(McpClientService::class);

        try {
            $tools = $service->listTools($client);

            return response()->json([
                'success' => true,
                'tools' => $tools,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 调用工具接口
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function callToolApi(Request $request, $id)
    {
        $client = McpClient::findOrFail($id);
        $toolName = $request->input('tool');
        $arguments = $request->input('arguments', []);

        if (! $toolName) {
            return response()->json([
                'success' => false,
                'message' => '工具名称不能为空',
            ], 400);
        }

        $service = app(McpClientService::class);

        try {
            $result = $service->callTool($client, $toolName, $arguments);

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
