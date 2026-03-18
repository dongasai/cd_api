<?php

namespace Database\Seeders;

use DB;
use Dcat\Admin\Models;
use Illuminate\Database\Seeder;

class AdminTablesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // base tables
        Models\Menu::truncate();
        Models\Menu::insert(
            [
                [
                    'id' => 50,
                    'parent_id' => 0,
                    'order' => 1,
                    'title' => 'dashboard',
                    'icon' => 'feather icon-bar-chart-2',
                    'uri' => '/',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:11',
                    'updated_at' => '2026-03-12 15:24:53',
                ],
                [
                    'id' => 51,
                    'parent_id' => 0,
                    'order' => 4,
                    'title' => 'channels',
                    'icon' => 'feather icon-server',
                    'uri' => '',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:11',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 52,
                    'parent_id' => 0,
                    'order' => 2,
                    'title' => 'api_keys',
                    'icon' => 'feather icon-key',
                    'uri' => 'api-keys',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:11',
                    'updated_at' => '2026-03-12 15:25:00',
                ],
                [
                    'id' => 54,
                    'parent_id' => 0,
                    'order' => 10,
                    'title' => 'model_management',
                    'icon' => 'feather icon-box',
                    'uri' => '',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:11',
                    'updated_at' => '2026-03-12 15:24:53',
                ],
                [
                    'id' => 55,
                    'parent_id' => 0,
                    'order' => 12,
                    'title' => 'logs',
                    'icon' => 'feather icon-file-text',
                    'uri' => '',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:11',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 56,
                    'parent_id' => 0,
                    'order' => 18,
                    'title' => 'system_settings',
                    'icon' => 'feather icon-settings',
                    'uri' => '',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:11',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 57,
                    'parent_id' => 51,
                    'order' => 5,
                    'title' => 'channel_list',
                    'icon' => '',
                    'uri' => 'channels',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:11',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 58,
                    'parent_id' => 51,
                    'order' => 6,
                    'title' => 'channel_groups',
                    'icon' => '',
                    'uri' => 'channel-groups',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:12',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 59,
                    'parent_id' => 51,
                    'order' => 7,
                    'title' => 'channel_tags',
                    'icon' => '',
                    'uri' => 'channel-tags',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:12',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 60,
                    'parent_id' => 51,
                    'order' => 8,
                    'title' => 'channel_models',
                    'icon' => '',
                    'uri' => 'channel-models',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:12',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 61,
                    'parent_id' => 51,
                    'order' => 9,
                    'title' => 'coding_accounts',
                    'icon' => '',
                    'uri' => 'coding-accounts',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:12',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 62,
                    'parent_id' => 54,
                    'order' => 11,
                    'title' => 'model_list',
                    'icon' => '',
                    'uri' => 'model-lists',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:12',
                    'updated_at' => '2026-03-12 15:24:53',
                ],
                [
                    'id' => 64,
                    'parent_id' => 55,
                    'order' => 13,
                    'title' => 'audit_logs',
                    'icon' => '',
                    'uri' => 'audit-logs',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:12',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 65,
                    'parent_id' => 55,
                    'order' => 14,
                    'title' => 'request_logs',
                    'icon' => '',
                    'uri' => 'request-logs',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:12',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 66,
                    'parent_id' => 55,
                    'order' => 15,
                    'title' => 'response_logs',
                    'icon' => '',
                    'uri' => 'response-logs',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:12',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 67,
                    'parent_id' => 55,
                    'order' => 16,
                    'title' => 'channel_request_logs',
                    'icon' => '',
                    'uri' => 'channel-request-logs',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:12',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 68,
                    'parent_id' => 55,
                    'order' => 17,
                    'title' => 'operation_logs',
                    'icon' => '',
                    'uri' => 'operation-logs',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:12',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 69,
                    'parent_id' => 56,
                    'order' => 19,
                    'title' => 'settings',
                    'icon' => '',
                    'uri' => 'system-settings',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:12',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 70,
                    'parent_id' => 56,
                    'order' => 20,
                    'title' => 'channel_affinity',
                    'icon' => '',
                    'uri' => 'channel-affinity-rules',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 14:46:12',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 71,
                    'parent_id' => 0,
                    'order' => 22,
                    'title' => 'system',
                    'icon' => 'feather icon-shield',
                    'uri' => '',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 15:24:00',
                    'updated_at' => '2026-03-12 15:24:53',
                ],
                [
                    'id' => 72,
                    'parent_id' => 71,
                    'order' => 23,
                    'title' => 'administrators',
                    'icon' => '',
                    'uri' => 'auth/users',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 15:24:00',
                    'updated_at' => '2026-03-12 15:24:53',
                ],
                [
                    'id' => 73,
                    'parent_id' => 71,
                    'order' => 24,
                    'title' => 'roles',
                    'icon' => '',
                    'uri' => 'auth/roles',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 15:24:00',
                    'updated_at' => '2026-03-12 15:24:53',
                ],
                [
                    'id' => 74,
                    'parent_id' => 71,
                    'order' => 25,
                    'title' => 'permissions',
                    'icon' => '',
                    'uri' => 'auth/permissions',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 15:24:00',
                    'updated_at' => '2026-03-12 15:24:53',
                ],
                [
                    'id' => 75,
                    'parent_id' => 71,
                    'order' => 26,
                    'title' => 'menu',
                    'icon' => '',
                    'uri' => 'auth/menu',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-12 15:24:00',
                    'updated_at' => '2026-03-12 15:24:53',
                ],
                [
                    'id' => 76,
                    'parent_id' => 56,
                    'order' => 21,
                    'title' => 'user_agents',
                    'icon' => 'fa-user-secret',
                    'uri' => 'user-agents',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-17 23:26:27',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 77,
                    'parent_id' => 0,
                    'order' => 3,
                    'title' => 'model_test',
                    'icon' => 'fa-flask',
                    'uri' => '',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-17 23:36:48',
                    'updated_at' => '2026-03-17 23:38:27',
                ],
                [
                    'id' => 78,
                    'parent_id' => 56,
                    'order' => 21,
                    'title' => 'preset_prompts',
                    'icon' => 'fa-comment-dots',
                    'uri' => 'preset-prompts',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-18 00:56:59',
                    'updated_at' => '2026-03-18 00:57:28',
                ],
                [
                    'id' => 79,
                    'parent_id' => 83,
                    'order' => 10,
                    'title' => 'channel_stats',
                    'icon' => 'fa-chart-bar',
                    'uri' => 'channel-stats',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-18 20:14:08',
                    'updated_at' => '2026-03-18 22:47:05',
                ],
                [
                    'id' => 81,
                    'parent_id' => 77,
                    'order' => 1,
                    'title' => 'model_test_old',
                    'icon' => '',
                    'uri' => 'model-test/old',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-18 21:35:30',
                    'updated_at' => '2026-03-18 21:35:30',
                ],
                [
                    'id' => 82,
                    'parent_id' => 77,
                    'order' => 2,
                    'title' => 'model_test_openai',
                    'icon' => '',
                    'uri' => 'model-test/openai',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-18 21:35:30',
                    'updated_at' => '2026-03-18 21:35:30',
                ],
                [
                    'id' => 83,
                    'parent_id' => 0,
                    'order' => 5,
                    'title' => 'data_statistics',
                    'icon' => 'fa-chart-pie',
                    'uri' => '',
                    'extension' => '',
                    'show' => 1,
                    'created_at' => '2026-03-18 22:47:05',
                    'updated_at' => '2026-03-18 22:47:05',
                ],
            ]
        );

        Models\Permission::truncate();
        Models\Permission::insert(
            [
                [
                    'id' => 1,
                    'name' => 'Auth management',
                    'slug' => 'auth-management',
                    'http_method' => '',
                    'http_path' => '',
                    'order' => 1,
                    'parent_id' => 0,
                    'created_at' => '2026-03-12 14:09:00',
                    'updated_at' => null,
                ],
                [
                    'id' => 2,
                    'name' => 'Users',
                    'slug' => 'users',
                    'http_method' => '',
                    'http_path' => '/auth/users*',
                    'order' => 2,
                    'parent_id' => 1,
                    'created_at' => '2026-03-12 14:09:00',
                    'updated_at' => null,
                ],
                [
                    'id' => 3,
                    'name' => 'Roles',
                    'slug' => 'roles',
                    'http_method' => '',
                    'http_path' => '/auth/roles*',
                    'order' => 3,
                    'parent_id' => 1,
                    'created_at' => '2026-03-12 14:09:00',
                    'updated_at' => null,
                ],
                [
                    'id' => 4,
                    'name' => 'Permissions',
                    'slug' => 'permissions',
                    'http_method' => '',
                    'http_path' => '/auth/permissions*',
                    'order' => 4,
                    'parent_id' => 1,
                    'created_at' => '2026-03-12 14:09:00',
                    'updated_at' => null,
                ],
                [
                    'id' => 5,
                    'name' => 'Menu',
                    'slug' => 'menu',
                    'http_method' => '',
                    'http_path' => '/auth/menu*',
                    'order' => 5,
                    'parent_id' => 1,
                    'created_at' => '2026-03-12 14:09:00',
                    'updated_at' => null,
                ],
                [
                    'id' => 6,
                    'name' => 'Extension',
                    'slug' => 'extension',
                    'http_method' => '',
                    'http_path' => '/auth/extensions*',
                    'order' => 6,
                    'parent_id' => 1,
                    'created_at' => '2026-03-12 14:09:00',
                    'updated_at' => null,
                ],
            ]
        );

        Models\Role::truncate();
        Models\Role::insert(
            [
                [
                    'id' => 1,
                    'name' => 'Administrator',
                    'slug' => 'administrator',
                    'created_at' => '2026-03-12 14:09:00',
                    'updated_at' => '2026-03-12 14:09:01',
                ],
            ]
        );

        Models\Setting::truncate();
        Models\Setting::insert(
            [

            ]
        );

        Models\Extension::truncate();
        Models\Extension::insert(
            [

            ]
        );

        Models\ExtensionHistory::truncate();
        Models\ExtensionHistory::insert(
            [

            ]
        );

        // pivot tables
        DB::table('admin_permission_menu')->truncate();
        DB::table('admin_permission_menu')->insert(
            [

            ]
        );

        DB::table('admin_role_menu')->truncate();
        DB::table('admin_role_menu')->insert(
            [

            ]
        );

        DB::table('admin_role_permissions')->truncate();
        DB::table('admin_role_permissions')->insert(
            [

            ]
        );

        // finish
    }
}
