<?php

namespace App\Services\Install;

use Illuminate\Support\Facades\DB;

/**
 * 配置服务
 *
 * 处理 .env 配置文件的保存和数据库连接测试
 */
class ConfigService
{
    /**
     * 测试数据库连接
     *
     * @param  array  $config  数据库配置
     * @return array 测试结果
     */
    public function testDatabaseConnection(array $config): array
    {
        try {
            if ($config['db_connection'] === 'sqlite') {
                $dbPath = database_path('database.sqlite');
                if (! file_exists($dbPath)) {
                    touch($dbPath);
                }

                $pdo = DB::connection('sqlite')->getPdo();
            } else {
                // 临时配置 MySQL 连接
                config([
                    'database.connections.test_mysql' => [
                        'driver' => 'mysql',
                        'host' => $config['db_host'],
                        'port' => $config['db_port'],
                        'database' => $config['db_database'],
                        'username' => $config['db_username'],
                        'password' => $config['db_password'] ?? '',
                        'charset' => 'utf8mb4',
                        'collation' => 'utf8mb4_unicode_ci',
                    ],
                ]);

                $pdo = DB::connection('test_mysql')->getPdo();
            }

            return [
                'success' => true,
                'message' => '数据库连接成功',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '数据库连接失败: '.$e->getMessage(),
            ];
        }
    }

    /**
     * 保存配置到 .env 文件
     *
     * @param  array  $config  配置数据
     * @return array 保存结果
     */
    public function save(array $config): array
    {
        try {
            $envFile = base_path('.env');
            $envContent = file_exists($envFile) ? file_get_contents($envFile) : '';

            // 更新配置项
            $envContent = $this->updateEnvValue($envContent, 'APP_URL', $config['app_url']);
            $envContent = $this->updateEnvValue($envContent, 'DB_CONNECTION', $config['db_connection']);

            if ($config['db_connection'] === 'mysql') {
                $envContent = $this->updateEnvValue($envContent, 'DB_HOST', $config['db_host']);
                $envContent = $this->updateEnvValue($envContent, 'DB_PORT', $config['db_port']);
                $envContent = $this->updateEnvValue($envContent, 'DB_DATABASE', $config['db_database']);
                $envContent = $this->updateEnvValue($envContent, 'DB_USERNAME', $config['db_username']);
                $envContent = $this->updateEnvValue($envContent, 'DB_PASSWORD', $config['db_password'] ?? '');
            }

            // 写入文件
            file_put_contents($envFile, $envContent);

            // 设置文件权限
            chmod($envFile, 0600);

            return [
                'success' => true,
                'message' => '配置保存成功',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '配置保存失败: '.$e->getMessage(),
            ];
        }
    }

    /**
     * 更新 .env 文件中的配置值
     *
     * @param  string  $content  .env 文件内容
     * @param  string  $key  配置键名
     * @param  string  $value  配置值
     * @return string 更新后的内容
     */
    private function updateEnvValue(string $content, string $key, string $value): string
    {
        $pattern = "/^{$key}=.*$/m";
        $replacement = "{$key}={$value}";

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $replacement, $content);
        } else {
            $content .= "\n{$replacement}\n";
        }

        return $content;
    }
}
