<?php

namespace App\Admin\Console;

use App\Models\Administrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * 重置管理员密码命令
 *
 * 将指定管理员的密码重置为默认值
 */
class ResetAdminPassword extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'cdapi:admin:reset-password {--username=admin : 要重置密码的用户名}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '重置管理员密码为默认值';

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $username = $this->option('username');

        // 查找管理员账户
        $admin = Administrator::where('username', $username)->first();

        if (! $admin) {
            $this->error("找不到用户名为 '{$username}' 的管理员账户");

            return self::FAILURE;
        }

        // 重置密码
        $admin->password = Hash::make('admin');
        $admin->save();

        $this->info("✓ 管理员 '{$username}' 的密码已重置为: admin");

        return self::SUCCESS;
    }
}
