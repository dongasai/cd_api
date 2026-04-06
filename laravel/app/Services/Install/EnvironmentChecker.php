<?php

namespace App\Services\Install;

/**
 * 环境检测服务
 *
 * 检测 PHP 版本、扩展、目录权限等
 */
class EnvironmentChecker
{
    /**
     * 执行所有环境检测
     *
     * @return array 检测结果数组
     */
    public function check(): array
    {
        return [
            'php' => $this->checkPhpVersion(),
            'extensions' => $this->checkExtensions(),
            'directories' => $this->checkDirectories(),
            'env_file' => $this->checkEnvFile(),
        ];
    }

    /**
     * 检测 PHP 版本
     */
    private function checkPhpVersion(): array
    {
        $version = PHP_VERSION;
        $required = '8.2';
        $passed = version_compare($version, $required, '>=');

        return [
            [
                'name' => 'PHP 版本',
                'required' => ">= {$required}",
                'actual' => $version,
                'status' => $passed,
                'message' => $passed ? '满足要求' : "PHP 版本需要 >= {$required}",
            ],
        ];
    }

    /**
     * 检测必需的 PHP 扩展
     */
    private function checkExtensions(): array
    {
        $required = ['PDO', 'Mbstring', 'OpenSSL', 'Curl', 'JSON'];
        $results = [];

        foreach ($required as $ext) {
            $loaded = extension_loaded($ext);
            $results[] = [
                'name' => "{$ext} 扩展",
                'required' => '已加载',
                'actual' => $loaded ? '已加载' : '未加载',
                'status' => $loaded,
                'message' => $loaded ? '满足要求' : "请安装 {$ext} 扩展",
            ];
        }

        return $results;
    }

    /**
     * 检测目录权限
     */
    private function checkDirectories(): array
    {
        $directories = [
            storage_path() => 'storage 目录',
            base_path('bootstrap/cache') => 'bootstrap/cache 目录',
        ];

        $results = [];

        foreach ($directories as $path => $name) {
            $writable = is_writable($path);
            $results[] = [
                'name' => $name,
                'required' => '可写',
                'actual' => $writable ? '可写' : '不可写',
                'status' => $writable,
                'message' => $writable ? '满足要求' : "请设置 {$name} 为可写权限",
            ];
        }

        return $results;
    }

    /**
     * 检测 .env 文件状态
     */
    private function checkEnvFile(): array
    {
        $envFile = base_path('.env');
        $exists = file_exists($envFile);

        return [
            [
                'name' => '.env 文件',
                'required' => '存在',
                'actual' => $exists ? '存在' : '不存在',
                'status' => true, // 已在第一步自动创建，这里必定为 true
                'message' => $exists ? '满足要求' : '将在配置步骤创建',
            ],
        ];
    }
}
