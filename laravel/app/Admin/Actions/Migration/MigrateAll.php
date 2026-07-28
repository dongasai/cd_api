<?php

namespace App\Admin\Actions\Migration;

use Dcat\Admin\Grid\Tools\AbstractTool;

/**
 * 执行全部待执行迁移工具按钮
 */
class MigrateAll extends AbstractTool
{
    /**
     * 按钮标题
     */
    public function title()
    {
        return '<i class="feather icon-zap"></i> '.admin_trans('admin-migration.actions.migrate_all');
    }

    /**
     * 渲染按钮
     */
    public function html()
    {
        $title = $this->title();
        $confirmTitle = e(admin_trans('admin-migration.confirm.migrate_all'));
        $confirmDesc = e(admin_trans('admin-migration.confirm.migrate_all_desc'));
        $url = e(admin_url('migrations/migrate-all'));

        return <<<HTML
<a class="btn btn-warning btn-sm" href="javascript:void(0);"
   onclick="migrateAllConfirm()" style="margin-right:5px;">
    {$title}
</a>
<script>
function migrateAllConfirm() {
    Swal.fire({
        title: '{$confirmTitle}',
        text: '{$confirmDesc}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: '确定',
        cancelButtonText: '取消'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '{$url}',
                type: 'POST',
                data: {_token: Dcat.token},
                success: function(data) {
                    if (data.status) {
                        Dcat.success(data.message || '操作成功');
                        setTimeout(function(){ location.reload(); }, 1000);
                    } else {
                        Dcat.error(data.message || '操作失败');
                    }
                },
                error: function() {
                    Dcat.error('请求失败');
                }
            });
        }
    });
}
</script>
HTML;
    }
}
