<?php

namespace App\Admin\Controllers;

use App\Models\CodingAccount;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;

/**
 * Coding账户管理控制器
 */
class CodingAccountController extends AdminController
{
    /**
     * 获取模型标题
     *
     * @return string
     */
    protected function title()
    {
        return 'Coding账户管理';
    }

    /**
     * 列表页面
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(CodingAccount::class, function (Grid $grid) {
            // 默认排序
            $grid->model()->orderBy('id', 'desc');

            // 列字段
            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '账户名称')->limit(30);
            $grid->column('platform', '平台')->display(function ($platform) {
                return CodingAccount::getPlatforms()[$platform] ?? $platform;
            });

            // 状态列 - 使用标签显示不同颜色
            $grid->column('status', '状态')->using(CodingAccount::getStatuses())
                ->dot(
                    [
                        CodingAccount::STATUS_ACTIVE => 'success',
                        CodingAccount::STATUS_WARNING => 'warning',
                        CodingAccount::STATUS_CRITICAL => 'danger',
                        CodingAccount::STATUS_EXHAUSTED => 'gray',
                        CodingAccount::STATUS_EXPIRED => 'gray',
                        CodingAccount::STATUS_SUSPENDED => 'gray',
                        CodingAccount::STATUS_ERROR => 'danger',
                    ],
                    'default' // 默认颜色
                );

            $grid->column('last_sync_at', '最后同步时间')->sortable();
            $grid->column('expires_at', '过期时间')->sortable();
            $grid->column('created_at', '创建时间')->sortable();

            // 筛选器
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id', 'ID');
                $filter->like('name', '账户名称');
                $filter->equal('status', '状态')->select(CodingAccount::getStatuses());
                $filter->equal('platform', '平台')->select(CodingAccount::getPlatforms());
                $filter->between('last_sync_at', '最后同步时间')->datetime();
                $filter->between('expires_at', '过期时间')->datetime();
                $filter->between('created_at', '创建时间')->datetime();
            });

            // 快速搜索
            $grid->quickSearch(['id', 'name', 'platform']);

            // 操作按钮
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                // 查看按钮
                $actions->append('<a href="'.route('dcat.admin.coding-accounts.show', $actions->getKey()).'" class="btn btn-primary btn-sm" style="margin-left: 5px;"><i class="feather icon-eye"></i> 查看</a>');
            });

            // 批量操作
            $grid->batchActions(function (Grid\Tools\BatchActions $batch) {
                $batch->disableDelete();
            });

            // 工具按钮
            $grid->tools(function (Grid\Tools $tools) {
                $tools->batch(function (Grid\Tools\BatchActions $actions) {
                    $actions->disableDelete();
                });
            });
        });
    }

    /**
     * 详情页面
     *
     * @param  mixed  $id
     * @return Show
     */
    protected function detail($id)
    {
        return Show::make($id, CodingAccount::class, function (Show $show) {
            $show->field('id', 'ID');
            $show->field('name', '账户名称');
            $show->field('platform', '平台')->as(function ($platform) {
                return CodingAccount::getPlatforms()[$platform] ?? $platform;
            });
            $show->field('driver_class', '驱动类');
            $show->field('status', '状态')->as(function ($status) {
                return CodingAccount::getStatuses()[$status] ?? $status;
            });

            // JSON 字段格式化显示
            $show->field('credentials', '凭据配置')->json();
            $show->field('quota_config', '配额配置')->json();
            $show->field('quota_cached', '配额缓存')->json();
            $show->field('config', '扩展配置')->json();

            $show->field('last_sync_at', '最后同步时间');
            $show->field('sync_error', '同步错误信息');
            $show->field('sync_error_count', '连续同步错误次数');
            $show->field('expires_at', '过期时间');
            $show->field('disabled_at', '禁用时间');
            $show->field('created_at', '创建时间');
            $show->field('updated_at', '更新时间');
        });
    }

    /**
     * 表单页面
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(CodingAccount::class, function (Form $form) {
            // 基本信息
            $form->display('id', 'ID');

            $form->text('name', '账户名称')
                ->required()
                ->maxLength(255)
                ->help('账户的显示名称，便于识别');

            $form->select('platform', '平台')
                ->options(CodingAccount::getPlatforms())
                ->required()
                ->help('选择账户所属的平台类型');

            $form->text('driver_class', '驱动类')
                ->required()
                ->help('驱动的完整类名，例如：App\\Drivers\\AliyunDriver');

            // 凭据配置
            $form->textarea('credentials', '凭据配置')
                ->rows(5)
                ->help('JSON格式的凭据信息，例如：{"api_key": "xxx", "api_secret": "xxx"}')
                ->customFormat(function ($value) {
                    if (empty($value)) {
                        return '';
                    }
                    if (is_array($value)) {
                        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    }

                    return $value;
                })
                ->saving(function ($value) {
                    if (empty($value)) {
                        return [];
                    }
                    $decoded = json_decode($value, true);

                    return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
                });

            // 状态
            $form->select('status', '状态')
                ->options(CodingAccount::getStatuses())
                ->default(CodingAccount::STATUS_ACTIVE)
                ->required();

            // 配额配置
            $form->textarea('quota_config', '配额配置')
                ->rows(8)
                ->help('JSON格式的配额配置，例如：{"limits": {}, "thresholds": {"warning": 0.8, "critical": 0.9}, "cycle": "monthly"}')
                ->customFormat(function ($value) {
                    if (empty($value)) {
                        return '';
                    }
                    if (is_array($value)) {
                        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    }

                    return $value;
                })
                ->saving(function ($value) {
                    if (empty($value)) {
                        return null;
                    }
                    $decoded = json_decode($value, true);

                    return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
                });

            // 配额缓存
            $form->textarea('quota_cached', '配额缓存')
                ->rows(5)
                ->help('JSON格式的配额缓存信息（系统自动维护）')
                ->customFormat(function ($value) {
                    if (empty($value)) {
                        return '';
                    }
                    if (is_array($value)) {
                        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    }

                    return $value;
                })
                ->saving(function ($value) {
                    if (empty($value)) {
                        return null;
                    }
                    $decoded = json_decode($value, true);

                    return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
                });

            // 扩展配置
            $form->textarea('config', '扩展配置')
                ->rows(5)
                ->help('JSON格式的驱动特定配置')
                ->customFormat(function ($value) {
                    if (empty($value)) {
                        return '';
                    }
                    if (is_array($value)) {
                        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    }

                    return $value;
                })
                ->saving(function ($value) {
                    if (empty($value)) {
                        return null;
                    }
                    $decoded = json_decode($value, true);

                    return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
                });

            // 时间字段
            $form->datetime('last_sync_at', '最后同步时间');
            $form->datetime('expires_at', '过期时间');

            // 同步错误信息
            $form->textarea('sync_error', '同步错误信息')
                ->rows(3)
                ->help('记录最近一次同步失败的错误信息');

            $form->number('sync_error_count', '连续同步错误次数')
                ->default(0)
                ->min(0);

            // 显示时间
            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');

            // 保存前验证 JSON 格式
            $form->saving(function (Form $form) {
                // 验证 credentials JSON 格式
                $credentials = $form->input('credentials');
                if (! empty($credentials) && is_string($credentials)) {
                    $decoded = json_decode($credentials, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return $form->response()->error('凭据配置 JSON 格式错误');
                    }
                    $form->input('credentials', $decoded);
                }

                // 验证 quota_config JSON 格式
                $quotaConfig = $form->input('quota_config');
                if (! empty($quotaConfig) && is_string($quotaConfig)) {
                    $decoded = json_decode($quotaConfig, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return $form->response()->error('配额配置 JSON 格式错误');
                    }
                    $form->input('quota_config', $decoded);
                }

                // 验证 config JSON 格式
                $config = $form->input('config');
                if (! empty($config) && is_string($config)) {
                    $decoded = json_decode($config, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return $form->response()->error('扩展配置 JSON 格式错误');
                    }
                    $form->input('config', $decoded);
                }
            });

            // 删除按钮确认
            $form->confirm('确定要删除此账户吗？删除后无法恢复！');
        });
    }
}
