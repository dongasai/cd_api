<?php

namespace App\Services\Install;

use App\Models\Administrator;
use Dcat\Admin\Models\Role;

/**
 * 管理员服务
 *
 * 初始化后台数据并创建管理员账号
 */
class AdminService
{
    /**
     * 初始化后台数据并创建管理员
     *
     * @param  array  $data  管理员数据
     * @return array 创建结果
     */
    public function initialize(array $data): array
    {
        try {
            // 先执行数据初始化 Seeder
            $migrationService = new MigrationService;
            $seedResult = $migrationService->seed();

            if (! $seedResult['success']) {
                return $seedResult;
            }

            // 创建管理员账号
            $admin = Administrator::create([
                'username' => $data['username'],
                'password' => bcrypt($data['password']),
                'name' => $data['name'],
            ]);

            // 关联 Administrator 角色
            $role = Role::where('slug', 'administrator')->first();
            if ($role) {
                $admin->roles()->attach($role->id);
            }

            return [
                'success' => true,
                'message' => '管理员创建成功',
                'admin' => [
                    'username' => $admin->username,
                    'name' => $admin->name,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '管理员创建失败: '.$e->getMessage(),
            ];
        }
    }
}
