<?php

namespace App\Admin\Controllers;

use App\Admin\Widgets\Metrics\Demo\NewDevices;
use App\Admin\Widgets\Metrics\Demo\NewUsers;
use App\Admin\Widgets\Metrics\Demo\ProductOrders;
use App\Admin\Widgets\Metrics\Demo\Sessions;
use App\Admin\Widgets\Metrics\Demo\Tickets;
use App\Admin\Widgets\Metrics\Demo\TotalUsers;
use Dcat\Admin\Layout\Column;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Layout\Row;

/**
 * 原始 Demo 仪表盘页面
 */
class HomeOldController
{
    public function index(Content $content)
    {
        return $content
            ->header('Dashboard (Original Demo)')
            ->description('Dcat Admin 原始示例')
            ->body(function (Row $row) {
                $row->column(6, function (Column $column) {
                    $column->row(new Tickets);
                    $column->row(new ProductOrders);
                });

                $row->column(6, function (Column $column) {
                    $column->row(function (Row $row) {
                        $row->column(6, new NewUsers);
                        $row->column(6, new NewDevices);
                    });

                    $column->row(new Sessions);
                    $column->row(new TotalUsers);
                });
            });
    }
}
