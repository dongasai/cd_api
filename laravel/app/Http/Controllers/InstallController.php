<?php

namespace App\Http\Controllers;

use App\Services\Install\AdminService;
use App\Services\Install\ConfigService;
use App\Services\Install\EnvironmentChecker;
use App\Services\Install\MigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 安装控制器
 *
 * 处理系统安装流程的各个步骤
 */
class InstallController extends Controller
{
    /**
     * 检查是否已安装
     */
    private function checkInstalled(): ?JsonResponse
    {
        if (file_exists(storage_path('installed.lock'))) {
            return response()->json(['success' => false, 'message' => '系统已安装'], 403);
        }

        return null;
    }

    /**
     * 安装首页 - 检测安装状态并初始化
     */
    public function index(): View
    {
        // 检查安装锁文件
        if (file_exists(storage_path('installed.lock'))) {
            return redirect('/')->with('error', '系统已安装');
        }

        // 检查 .env 文件
        $envFile = base_path('.env');
        $envExample = base_path('.env.example');
        $hasEnvFile = file_exists($envFile);

        // 如果 .env 不存在但 .env.example 存在，复制一份
        if (! $hasEnvFile && file_exists($envExample)) {
            copy($envExample, $envFile);
            $hasEnvFile = true;
        }

        // 检查 APP_KEY 是否存在
        $appKey = config('app.key');
        $hasAppKey = ! empty($appKey);

        return view('install.index', compact('hasAppKey', 'hasEnvFile'));
    }

    /**
     * 环境检测页面
     */
    public function environment(): View
    {
        return view('install.environment');
    }

    /**
     * 执行环境检测 API
     */
    public function checkEnvironment(): JsonResponse
    {
        if ($response = $this->checkInstalled()) {
            return $response;
        }

        $checker = new EnvironmentChecker;
        $results = $checker->check();

        $allPassed = collect($results)->every(function ($group) {
            return collect($group)->every(fn ($item) => $item['status']);
        });

        return response()->json([
            'success' => true,
            'results' => $results,
            'all_passed' => $allPassed,
        ]);
    }

    /**
     * 配置填写页面
     */
    public function config(): View
    {
        // 获取当前配置作为默认值
        $currentConfig = [
            'app_url' => config('app.url'),
            'db_connection' => config('database.default'),
            'db_host' => config('database.connections.mysql.host', '127.0.0.1'),
            'db_port' => config('database.connections.mysql.port', 3306),
            'db_database' => config('database.connections.mysql.database', 'laravel'),
            'db_username' => config('database.connections.mysql.username', 'root'),
            'db_password' => config('database.connections.mysql.password', ''),
        ];

        return view('install.config', compact('currentConfig'));
    }

    /**
     * 测试数据库连接 API
     */
    public function testDatabaseConnection(Request $request): JsonResponse
    {
        if ($response = $this->checkInstalled()) {
            return $response;
        }

        $config = $request->validate([
            'db_connection' => 'required|in:mysql,sqlite',
            'db_host' => 'required_if:db_connection,mysql',
            'db_port' => 'required_if:db_connection,mysql|numeric',
            'db_database' => 'required_if:db_connection,mysql',
            'db_username' => 'required_if:db_connection,mysql',
            'db_password' => 'nullable',
        ]);

        $configService = new ConfigService;
        $result = $configService->testDatabaseConnection($config);

        return response()->json($result);
    }

    /**
     * 保存配置 API
     */
    public function saveConfig(Request $request): JsonResponse
    {
        if ($response = $this->checkInstalled()) {
            return $response;
        }

        $config = $request->validate([
            'app_url' => 'required|url',
            'db_connection' => 'required|in:mysql,sqlite',
            'db_host' => 'required_if:db_connection,mysql',
            'db_port' => 'required_if:db_connection,mysql|numeric',
            'db_database' => 'required_if:db_connection,mysql',
            'db_username' => 'required_if:db_connection,mysql',
            'db_password' => 'nullable',
        ]);

        $configService = new ConfigService;
        $result = $configService->save($config);

        return response()->json($result);
    }

    /**
     * 数据库迁移页面
     */
    public function migrate(): View
    {
        return view('install.migrate');
    }

    /**
     * 数据库检查页面
     */
    public function databaseCheck(): View
    {
        // 检查数据库是否有表
        $existingTables = $this->getExistingTables();
        $hasData = count($existingTables) > 0;

        return view('install.database-check', compact('hasData', 'existingTables'));
    }

    /**
     * 检查数据库中已有的表
     */
    private function getExistingTables(): array
    {
        try {
            $tables = DB::select('SHOW TABLES');
            $tableNames = [];
            foreach ($tables as $table) {
                $tableNames[] = array_values((array) $table)[0];
            }

            return $tableNames;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 清空数据库
     */
    public function cleanDatabase(): JsonResponse
    {
        if ($response = $this->checkInstalled()) {
            return $response;
        }

        try {
            $tables = $this->getExistingTables();
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($tables as $table) {
                DB::statement("DROP TABLE IF EXISTS `{$table}`");
            }
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            return response()->json([
                'success' => true,
                'message' => '数据库已清空',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '清空失败: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * 执行数据库迁移 API
     */
    public function executeMigrate(): JsonResponse
    {
        if ($response = $this->checkInstalled()) {
            return $response;
        }

        $migrationService = new MigrationService;
        $result = $migrationService->migrate();

        return response()->json($result);
    }

    /**
     * 获取待执行的迁移列表
     */
    public function getPendingMigrations(): JsonResponse
    {
        $migrationService = new MigrationService;
        $pending = $migrationService->getPendingMigrations();

        return response()->json([
            'success' => true,
            'pending' => $pending,
            'count' => count($pending),
        ]);
    }

    /**
     * 执行单个迁移
     */
    public function migrateOne(Request $request): JsonResponse
    {
        $migrationName = $request->input('migration');
        if (! $migrationName) {
            return response()->json([
                'success' => false,
                'message' => '缺少迁移文件名',
            ]);
        }

        $migrationService = new MigrationService;
        $result = $migrationService->migrateOne($migrationName);

        return response()->json($result);
    }

    /**
     * 创建管理员页面
     */
    public function admin(): View
    {
        return view('install.admin');
    }

    /**
     * 创建管理员 API
     */
    public function createAdmin(Request $request): JsonResponse
    {
        if ($response = $this->checkInstalled()) {
            return $response;
        }

        $data = $request->validate([
            'username' => 'required|string|min:3|max:50|unique:admin_users',
            'password' => 'required|string|min:8',
            'name' => 'required|string|max:100',
        ]);

        $adminService = new AdminService;
        $result = $adminService->initialize($data);

        return response()->json($result);
    }

    /**
     * 安装完成页面
     */
    public function complete(): View
    {
        // 创建安装锁文件
        file_put_contents(storage_path('installed.lock'), date('Y-m-d H:i:s'));

        return view('install.complete');
    }

    /**
     * 生成 APP_KEY
     *
     * 当 APP_KEY 缺失时，通过此接口生成
     */
    public function generateKey(): JsonResponse
    {
        $envFile = base_path('.env');

        if (! file_exists($envFile)) {
            return response()->json([
                'success' => false,
                'message' => '.env 文件不存在',
            ], 500);
        }

        $content = file_get_contents($envFile);
        $key = 'base64:'.base64_encode(random_bytes(32));

        // 替换 APP_KEY 行
        if (preg_match('/^APP_KEY\s*=/m', $content)) {
            $content = preg_replace('/^APP_KEY\s*=.*$/m', 'APP_KEY='.$key, $content);
        } else {
            $content = rtrim($content)."\nAPP_KEY={$key}\n";
        }

        file_put_contents($envFile, $content);

        return response()->json([
            'success' => true,
            'message' => 'APP_KEY 已生成',
            'key' => $key,
        ]);
    }
}
