# OpenAPI 规范验证工具方案

## 问题

用户需要一个成熟的工具来验证请求体是否符合下载的OpenAPI规范。

## 解决方案：opis/json-schema

[opis/json-schema](https://github.com/opis/json-schema) 是一个现代化的PHP JSON Schema验证库，**完全支持JSON Schema draft-2020-12**，这正是OpenAPI 3.1.0所使用的标准。

### 为什么选择opis/json-schema？

1. ✅ **完全兼容OpenAPI 3.1.0** - 支持draft-2020-12、draft-2019-09、draft-07、draft-06
2. ✅ **PHP原生实现** - 无需依赖外部服务或网关
3. ✅ **成熟稳定** - GitHub活跃维护，文档完善
4. ✅ **Laravel友好** - 可轻松集成到Laravel项目中
5. ✅ **错误处理增强** - 可配合 `m1x0n/opis-json-schema-error-presenter` 提供友好的错误信息

## 安装方法

```bash
cd laravel
composer require opis/json-schema
```

可选：安装错误提示增强包

```bash
composer require m1x0n/opis-json-schema-error-presenter
```

## 基本使用示例

### 1. 从OpenAPI规范中提取Schema

```php
<?php

use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Validator;

// 加载OpenAPI规范文件
$openapiYaml = file_get_contents(storage_path('openai-openapi.yml'));

// 解析YAML获取JSON Schema定义
// 注意：需要使用YAML解析器（如symfony/yaml）
$openapiArray = \Symfony\Component\Yaml\Yaml::parse($openapiYaml);

// 提取CreateChatCompletionRequest的schema定义
$requestSchema = $openapiArray['components']['schemas']['CreateChatCompletionRequest'];

// 将schema转换为JSON字符串
$schemaJson = json_encode($requestSchema);

// 创建Schema对象
$schema = Schema::fromJsonString($schemaJson);
```

### 2. 验证请求体

```php
<?php

use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Validator;

// 准备要验证的请求体数据
$requestBody = [
    'model' => 'gpt-4',
    'messages' => [
        ['role' => 'user', 'content' => 'Hello']
    ],
    'temperature' => 0.7,
];

// 转换为PHP对象（JSON Schema验证需要对象而非数组）
$data = json_decode(json_encode($requestBody));

// 加载schema（见上面的提取步骤）
$schema = Schema::fromJsonString($schemaJson);

// 创建验证器并执行验证
$validator = new Validator();
$result = $validator->schemaValidation($data, $schema);

// 检查验证结果
if ($result->isValid()) {
    echo "✅ 请求体验证通过！\n";
} else {
    echo "❌ 请求体验证失败：\n";

    // 获取第一个错误
    $error = $result->getFirstError();
    echo "  错误位置: " . $error->dataPointer() . "\n";
    echo "  错误信息: " . $error->message() . "\n";

    // 获取所有错误
    $errors = $result->getErrors();
    foreach ($errors as $error) {
        echo "  - [{$error->dataPointer()}] {$error->message()}\n";
    }
}
```

### 3. 集成错误提示增强包（可选）

```php
<?php

use Opis\JsonSchema\Validator;
use OpisErrorPresenter\Implementation\ValidationErrorPresenter;
use OpisErrorPresenter\Implementation\MessageFormatterFactory;

$validator = new Validator();
$result = $validator->schemaValidation($data, $schema);

if (!$result->isValid()) {
    // 创建错误展示器
    $presenter = new ValidationErrorPresenter(
        new MessageFormatterFactory(),
        new PresentedValidationErrorFactory()
    );

    // 获取友好的错误信息
    $presentedErrors = $presenter->present($result->getErrors());

    foreach ($presentedErrors as $error) {
        echo "字段: " . $error->getField() . "\n";
        echo "错误: " . $error->getMessage() . "\n";
        echo "建议: " . $error->getSuggestion() . "\n";
    }
}
```

## 创建Console命令

建议创建一个Laravel Console命令来方便使用：

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Validator;

class ValidateApiRequest extends Command
{
    protected $signature = 'cdapi:validate-request
                            {--protocol=openai : 协议类型 (openai|anthropic)}
                            {--request-id= : 从请求日志ID验证}
                            {--file= : 从JSON文件验证}
                            {--json= : 直接验证JSON字符串}';

    protected $description = '根据OpenAPI规范验证API请求体';

    public function handle(): int
    {
        // 1. 加载对应的OpenAPI规范
        $specFile = $this->option('protocol') === 'openai'
            ? storage_path('openai-openapi.yml')
            : storage_path('anthropic-openapi.yml');

        // 2. 提取请求Schema
        // 3. 加载请求体数据
        // 4. 执行验证
        // 5. 输出结果

        return self::SUCCESS;
    }
}
```

## 实现步骤

1. **安装依赖包**
   ```bash
   composer require opis/json-schema
   composer require symfony/yaml  # 用于解析YAML
   ```

2. **创建验证命令**
   - 文件：`app/Console/Commands/ValidateApiRequest.php`
   - 功能：加载OpenAPI规范、提取schema、验证请求体、输出详细错误

3. **集成到请求处理流程**
   - 在ProxyServer中可选调用验证器
   - 提供开发/调试模式的验证支持

4. **测试验证**
   - 使用已知的正确请求体测试
   - 使用错误的请求体测试错误提示

## 其他成熟工具对比

| 工具 | 支持版本 | PHP支持 | 优势 | 缺点 |
|------|----------|---------|------|------|
| **opis/json-schema** | draft-2020-12, 2019-09, 07, 06 | ✅ 原生 | 完全支持OpenAPI 3.1 | 需手动提取schema |
| justinrainbow/json-schema | draft-04, 03 | ✅ 原生 | 老牌稳定 | **不支持**OpenAPI 3.1 |
| swaggest/php-json-schema | draft-04, 06, 07 | ✅ 原生 | 支持代码生成 | 不支持draft-2020-12 |
| Apache APISIX request-validation | JSON Schema | ❌ 网关层 | 网关集成 | 需要APISIX网关 |
| Kong Request Validator | draft-04 | ❌ 网关层 | 网关集成 | 需要Kong网关 |

**结论**：`opis/json-schema` 是目前最适合PHP/Laravel项目的OpenAPI 3.1验证工具。

## 参考资源

- [opis/json-schema GitHub](https://github.com/opis/json-schema)
- [opis/json-schema 文档](https://opis.io/json-schema/1.x/)
- [m1x0n/opis-json-schema-error-presenter](https://github.com/m1x0n/opis-json-schema-error-presenter)
- [JSON Schema 2020-12 规范](https://json-schema.org/draft/2020-12/schema)
- [OpenAPI 3.1.0 规范](https://spec.openapis.org/oas/v3.1.0)