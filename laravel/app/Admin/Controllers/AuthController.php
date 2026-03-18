<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Http\Controllers\AuthController as BaseAuthController;
use Dcat\Admin\Http\Repositories\Administrator;
use Dcat\Admin\Layout\Content;

/**
 * 自定义认证控制器
 *
 * 扩展 dcat-admin 的认证控制器，添加语言设置功能
 */
class AuthController extends BaseAuthController
{
    /**
     * 显示用户设置页面
     *
     * @return Content
     */
    public function getSetting(Content $content)
    {
        $form = $this->settingForm();
        $form->tools(
            function (Form\Tools $tools) {
                $tools->disableList();
            }
        );

        return $content
            ->title(trans('admin.user_setting'))
            ->description(trans('admin.edit'))
            ->body($form->edit(Admin::user()->getKey()));
    }

    /**
     * 构建设置表单
     *
     * @return Form
     */
    protected function settingForm()
    {
        return new Form(new Administrator, function (Form $form) {
            $form->action(admin_url('auth/setting'));

            $form->disableCreatingCheck();
            $form->disableEditingCheck();
            $form->disableViewCheck();

            $form->tools(function (Form\Tools $tools) {
                $tools->disableView();
                $tools->disableDelete();
            });

            $form->display('username', trans('admin.username'));
            $form->text('name', trans('admin.name'))->required();
            $form->image('avatar', trans('admin.avatar'))->autoUpload();

            // 添加语言选择
            $form->select('language', trans('admin.language'))
                ->options(\App\Models\Administrator::getSupportedLanguages())
                ->help(trans('admin.language_help'));

            $form->password('old_password', trans('admin.old_password'));

            $form->password('password', trans('admin.password'))
                ->minLength(5)
                ->maxLength(20)
                ->customFormat(function ($v) {
                    if ($v == $this->password) {
                        return;
                    }

                    return $v;
                });
            $form->password('password_confirmation', trans('admin.password_confirmation'))->same('password');

            $form->ignore(['password_confirmation', 'old_password']);

            $form->saving(function (Form $form) {
                if ($form->password && $form->model()->password != $form->password) {
                    $form->password = bcrypt($form->password);
                }

                if (! $form->password) {
                    $form->deleteInput('password');
                }
            });

            $form->saved(function (Form $form) {
                // 更新用户语言设置后，设置应用语言
                $language = $form->model()->language ?? 'zh_CN';
                app()->setLocale($language);

                return $form
                    ->response()
                    ->success(trans('admin.update_succeeded'))
                    ->redirect('auth/setting');
            });
        });
    }
}
