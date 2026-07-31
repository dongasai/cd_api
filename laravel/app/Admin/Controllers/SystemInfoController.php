<?php

namespace App\Admin\Controllers;

use App\Services\SystemInfoService;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;

/**
 * 系统信息控制器
 *
 * 在后台展示系统版本信息（与 php artisan version 输出一致）
 */
class SystemInfoController
{
    /**
     * 系统信息页面
     */
    public function index(Content $content, SystemInfoService $service)
    {
        $info = $service->getAll();

        return $content
            ->header('系统信息')
            ->description('CdApi 版本与环境信息')
            ->body(function (Row $row) use ($info) {
                // 构建信息
                $row->column(12, $this->buildCard('构建信息', 'fa fa-cube', 'primary', [
                    '构建时间(UTC)' => $info['build']['build_time_utc'],
                    '构建时间(本地)' => $info['build']['build_time_local'],
                    '构建分支' => $info['build']['build_branch'],
                    '构建 Commit' => $info['build']['build_commit'],
                    '构建执行者' => $info['build']['build_runner'],
                ]));

                // 框架信息
                $row->column(12, $this->buildCard('框架信息', 'fa fa-code', 'info', [
                    'Laravel 版本' => $info['framework']['laravel_version'],
                    'PHP 版本' => $info['framework']['php_version'],
                    '应用名称' => $info['framework']['app_name'],
                ]));

                // 运行环境
                $row->column(12, $this->buildCard('运行环境', 'fa fa-server', 'success', [
                    '运行环境' => $info['runtime']['environment'],
                    '服务器时区' => $info['runtime']['timezone'],
                    '操作系统' => $info['runtime']['os'],
                    '运行方式' => $info['runtime']['sapi'],
                ]));

                // 数据库信息
                $row->column(12, $this->buildCard('数据库信息', 'fa fa-database', 'warning', [
                    '数据库连接' => $info['database']['connection'],
                    '数据库名称' => $info['database']['database_name'],
                    '数据库版本' => $info['database']['database_version'],
                    'Composer 版本' => $info['database']['composer_version'],
                ]));
            });
    }

    /**
     * 构建信息展示卡片
     */
    protected function buildCard(string $title, string $icon, string $style, array $items): Card
    {
        $rows = '';
        foreach ($items as $label => $value) {
            $rows .= <<<HTML
<tr>
    <td style="width:180px;font-weight:600">{$label}</td>
    <td>{$value}</td>
</tr>
HTML;
        }

        $content = <<<HTML
<table class="table table-bordered mb-0">
    <tbody>{$rows}</tbody>
</table>
HTML;

        return Card::make($title, $content)
            ->icon($icon)
            ->style($style);
    }
}
