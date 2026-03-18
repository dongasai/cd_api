# 渠道User-Agent限制功能设计方案

## 文档信息

- **创建日期**: 2026-03-17
- **功能名称**: 渠道User-Agent限制
- **设计版本**: v1.0
- **状态**: 待实现

---

## 1. 功能概述

### 1.1 业务背景

系统需要实现渠道级别的User-Agent访问控制，防止特定客户端访问某些渠道。当请求的User-Agent不在渠道允许列表中时，系统应跳过该渠道，尝试路由到其他可用渠道。

### 1.2 功能目标

- 实现渠道与User-Agent的多对多关联关系
- 支持正则表达式匹配User-Agent
- 在渠道选择阶段过滤不匹配的渠道
- 提供Admin管理界面进行配置

### 1.3 核心特性

- ✅ 独立的User-Agent规则表，支持规则复用
- ✅ 多条正则匹配规则，任意一条命中即可
- ✅ 渠道选择时自动跳过不匹配的渠道
- ✅ 与现有渠道亲和性功能协同工作

---

## 2. 系统架构

### 2.1 架构设计图

```
┌─────────────────────────────────────────────────────────────┐
│                        客户端请求                             │
│                   (携带 User-Agent Header)                   │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                    ChannelRouterService                      │
│                 (渠道路由服务 - 核心入口)                      │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                    ChannelSelector                           │
│                    (渠道选择器)                               │
└─────────────────────┬───────────────────────────────────────┘
                      │
        ┌─────────────┴─────────────┐
        │                           │
        ▼                           ▼
┌──────────────────┐      ┌──────────────────┐
│ UserAgentFilter  │      │ ChannelAffinity  │
│ (User-Agent过滤) │      │ (渠道亲和性)      │
└──────────────────┘      └──────────────────┘
        │                           │
        └─────────────┬─────────────┘
                      │
                      ▼
              ┌───────────────┐
              │ 可用渠道列表   │
              └───────────────┘
```

### 2.2 数据流向

```
1. 客户端发起请求，携带 User-Agent Header
   ↓
2. ChannelRouterService 接收请求
   ↓
3. ChannelSelector 获取候选渠道列表
   ↓
4. UserAgentFilter 过滤不匹配 User-Agent 的渠道
   ↓
5. ChannelAffinityService 检查渠道亲和性
   ↓
6. 选择最终渠道并转发请求
```

---

## 3. 数据库设计

### 3.1 user_agents 表

**用途**: 存储User-Agent正则匹配规则

| 字段 | 类型 | 说明 | 备注 |
|------|------|------|------|
| id | bigint unsigned | 主键 | 自增 |
| name | varchar(100) | 规则名称 | 如"Chrome浏览器" |
| patterns | json | 正则表达式数组 | 如["Chrome\\/[0-9]+", "CriOS\\/[0-9]+"] |
| description | text | 规则描述 | 可空 |
| is_enabled | tinyint(1) | 是否启用 | 默认true |
| hit_count | bigint unsigned | 命中次数 | 统计用，默认0 |
| last_hit_at | timestamp | 最后命中时间 | 可空 |
| created_at | timestamp | 创建时间 | |
| updated_at | timestamp | 更新时间 | |

**索引设计**:
- PRIMARY KEY (`id`)
- INDEX `idx_enabled` (`is_enabled`) - 查询启用的规则

**示例数据**:
```sql
INSERT INTO user_agents (name, patterns, description, is_enabled) VALUES
('Chrome浏览器', '["Chrome\\/[0-9]+", "CriOS\\/[0-9]+", "Mobile.*Chrome"]', '匹配Chrome浏览器（含桌面和移动端）', 1),
('Firefox浏览器', '["Firefox\\/[0-9]+", "Fxios\\/[0-9]+"]', '匹配Firefox浏览器（含桌面和移动端）', 1),
('Safari浏览器', '["Safari\\/[0-9]+(?!.*Chrome)", "Mobile.*Safari"]', '匹配Safari浏览器（不含Chrome）', 1),
('Postman客户端', '["PostmanRuntime\\/[0-9]+"]', '匹配Postman请求工具', 1),
('Python脚本', '["python-requests\\/[0-9]+", "Python\\/[0-9]+"]', '匹配Python脚本', 1),
('爬虫工具', '["(bot|crawler|spider|scraper)", "Googlebot\\/[0-9]+", "bingbot\\/[0-9]+"]', '匹配常见爬虫工具', 1);
```

---

### 3.2 channel_user_agent 表

**用途**: 渠道与User-Agent的中间表（多对多关系）

| 字段 | 类型 | 说明 | 备注 |
|------|------|------|------|
| channel_id | bigint unsigned | 渠道ID | 外键关联channels.id |
| user_agent_id | bigint unsigned | User-Agent ID | 外键关联user_agents.id |
| created_at | timestamp | 创建时间 | |
| updated_at | timestamp | 更新时间 | |

**索引设计**:
- PRIMARY KEY (`channel_id`, `user_agent_id`) - 联合主键
- INDEX `idx_channel_id` (`channel_id`) - 查询渠道的UA规则
- INDEX `idx_user_agent_id` (`user_agent_id`) - 查询UA规则被哪些渠道使用

**外键约束**:
```sql
FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
FOREIGN KEY (user_agent_id) REFERENCES user_agents(id) ON DELETE CASCADE
```

---

### 3.3 channels 表修改

**新增字段**:

| 字段 | 类型 | 说明 | 备注 |
|------|------|------|------|
| has_user_agent_restriction | tinyint(1) | 是否有UA限制 | 优化查询性能，默认false |

**索引**:
```sql
ALTER TABLE channels ADD COLUMN has_user_agent_restriction TINYINT(1) DEFAULT 0;
ALTER TABLE channels ADD INDEX idx_has_ua_restriction (has_user_agent_restriction);
```

**说明**:
- 该字段用于快速筛选有限制配置的渠道，避免对所有渠道都进行User-Agent检查
- 当渠道关联User-Agent规则时，自动设置为true；取消所有关联时设置为false

---

## 4. Model设计

### 4.1 UserAgent Model

**文件位置**: `laravel/app/Models/UserAgent.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * User-Agent规则模型
 *
 * @property int $id
 * @property string $name 规则名称
 * @property array $patterns 正则表达式数组
 * @property string|null $description 描述
 * @property bool $is_enabled 是否启用
 * @property int $hit_count 命中次数
 * @property \Carbon\Carbon|null $last_hit_at 最后命中时间
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class UserAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'patterns',
        'description',
        'is_enabled',
        'hit_count',
        'last_hit_at',
    ];

    protected $attributes = [
        'is_enabled' => true,
        'hit_count' => 0,
        'patterns' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'patterns' => 'array', // JSON数组
            'is_enabled' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }

    /**
     * 关联的渠道列表
     */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_user_agent', 'user_agent_id', 'channel_id')
            ->withTimestamps();
    }

    /**
     * 检查User-Agent是否匹配此规则（多条正则，任意一条命中即可）
     *
     * @param string $userAgent 请求的User-Agent字符串
     * @return bool true=匹配, false=不匹配
     */
    public function matches(string $userAgent): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        $patterns = $this->patterns ?? [];

        // 如果没有配置任何正则表达式，返回false
        if (empty($patterns)) {
            return false;
        }

        // 遍历所有正则表达式，任意一条匹配即返回true
        foreach ($patterns as $pattern) {
            try {
                if (@preg_match($pattern, $userAgent)) {
                    return true;
                }
            } catch (\Exception $e) {
                \Log::error('User-Agent正则匹配失败', [
                    'pattern' => $pattern,
                    'user_agent' => $userAgent,
                    'error' => $e->getMessage(),
                ]);
                // 继续尝试下一个正则表达式
                continue;
            }
        }

        return false;
    }

    /**
     * 记录命中
     */
    public function recordHit(): void
    {
        $this->increment('hit_count');
        $this->update(['last_hit_at' => now()]);
    }

    /**
     * 查询启用的规则
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * 获取正则表达式数量
     */
    public function getPatternCount(): int
    {
        return count($this->patterns ?? []);
    }
}
```

---

### 4.2 Channel Model 修改

**文件位置**: `laravel/app/Models/Channel.php`

**新增方法**:

```php
/**
 * 允许的User-Agent规则列表
 */
public function allowedUserAgents(): BelongsToMany
{
    return $this->belongsToMany(UserAgent::class, 'channel_user_agent', 'channel_id', 'user_agent_id')
        ->withTimestamps()
        ->where('is_enabled', true); // 只关联启用的规则
}

/**
 * 检查是否有User-Agent限制
 */
public function hasUserAgentRestriction(): bool
{
    return (bool) $this->has_user_agent_restriction;
}

/**
 * 检查请求的User-Agent是否被允许
 *
 * @param string $userAgent 请求的User-Agent
 * @return bool true=允许, false=不允许
 */
public function isUserAgentAllowed(string $userAgent): bool
{
    // 如果没有限制，允许所有User-Agent
    if (!$this->hasUserAgentRestriction()) {
        return true;
    }

    // 获取关联的User-Agent规则
    $allowedPatterns = $this->allowedUserAgents;

    // 如果有限制但未配置任何规则，拒绝访问
    if ($allowedPatterns->isEmpty()) {
        return false;
    }

    // 检查是否匹配任意一条规则
    foreach ($allowedPatterns as $pattern) {
        if ($pattern->matches($userAgent)) {
            $pattern->recordHit(); // 记录命中
            return true;
        }
    }

    return false; // 没有任何规则匹配
}
```

---

## 5. 核心服务设计

### 5.1 UserAgentFilterService

**文件位置**: `laravel/app/Services/Router/UserAgentFilterService.php`

**职责**: 负责User-Agent过滤逻辑，被ChannelSelector调用

```php
<?php

namespace App\Services\Router;

use App\Models\Channel;
use Illuminate\Support\Collection;

/**
 * User-Agent过滤服务
 *
 * 负责根据请求的User-Agent过滤渠道
 */
class UserAgentFilterService
{
    /**
     * 过滤不匹配User-Agent的渠道
     *
     * @param Collection $channels 候选渠道集合
     * @param string $userAgent 请求的User-Agent
     * @return Collection 过滤后的渠道集合
     */
    public function filterChannels(Collection $channels, string $userAgent): Collection
    {
        // 如果User-Agent为空，不过滤
        if (empty($userAgent)) {
            return $channels;
        }

        return $channels->filter(function (Channel $channel) use ($userAgent) {
            // 检查渠道是否允许该User-Agent
            $allowed = $channel->isUserAgentAllowed($userAgent);

            if (!$allowed) {
                \Log::info('渠道User-Agent不匹配，已跳过', [
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->name,
                    'user_agent' => $userAgent,
                ]);
            }

            return $allowed;
        });
    }
}
```

---

### 5.2 ChannelSelector 修改

**文件位置**: `laravel/app/Services/Router/ChannelSelector.php`

**集成点**: 在 `selectChannel()` 方法中调用UserAgentFilterService

```php
/**
 * 选择可用渠道
 */
public function selectChannel(Request $request): ?Channel
{
    // 1. 获取候选渠道列表（现有逻辑）
    $candidateChannels = $this->getCandidateChannels($request);

    if ($candidateChannels->isEmpty()) {
        return null;
    }

    // 2. User-Agent过滤（新增）
    $userAgent = $request->header('User-Agent', '');
    $userAgentFilter = app(UserAgentFilterService::class);
    $filteredChannels = $userAgentFilter->filterChannels($candidateChannels, $userAgent);

    if ($filteredChannels->isEmpty()) {
        \Log::warning('所有候选渠道均不匹配User-Agent', [
            'user_agent' => $userAgent,
            'candidate_count' => $candidateChannels->count(),
        ]);
        return null;
    }

    // 3. 渠道亲和性处理（现有逻辑）
    $affinityService = app(ChannelAffinityService::class);
    $channel = $affinityService->selectChannelWithAffinity($filteredChannels, $request);

    // 4. 返回选中的渠道
    return $channel;
}
```

---

## 6. Admin管理界面设计

### 6.1 UserAgentController

**文件位置**: `laravel/app/Admin/Controllers/UserAgentController.php`

**功能**: 管理User-Agent正则规则

```php
<?php

namespace App\Admin\Controllers;

use App\Models\UserAgent;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Form;
use Dcat Admin\Grid;
use Dcat\Admin\Show;

class UserAgentController extends AdminController
{
    protected $title = 'User-Agent规则管理';

    protected function grid()
    {
        return Grid::make(UserAgent::withCount('channels'), function (Grid $grid) {
            $grid->column('id', 'ID')->sortable();
            $grid->column('name', '规则名称');
            $grid->column('pattern', '正则表达式')->copy();
            $grid->column('channels_count', '关联渠道数');
            $grid->column('hit_count', '命中次数');
            $grid->column('last_hit_at', '最后命中时间');
            $grid->column('is_enabled', '状态')->switch();
            $grid->column('created_at', '创建时间');

            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id');
                $filter->like('name', '规则名称');
                $filter->equal('is_enabled', '状态')->select([0 => '禁用', 1 => '启用']);
            });

            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->disableView();
            });
        });
    }

    protected function form()
    {
        return Form::make(UserAgent::with('channels'), function (Form $form) {
            $form->display('id', 'ID');
            $form->text('name', '规则名称')->required();

            // 多条正则表达式（listField）
            $form->listField('patterns', '正则表达式列表')
                ->required()
                ->help('每行一个正则表达式，如：Chrome\\/[0-9]+（不需要添加分隔符）');

            $form->textarea('description', '描述');
            $form->switch('is_enabled', '是否启用')->default(true);

            // 关联渠道（多选）
            $form->multipleSelect('channels', '关联渠道')
                ->options(Channel::pluck('name', 'id'))
                ->customFormat(function ($v) {
                    return $v ? array_column($v, 'id') : [];
                })
                ->saving(function ($value) {
                    return $value;
                });

            $form->display('hit_count', '命中次数');
            $form->display('last_hit_at', '最后命中时间');
            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');
        });
    }
}
```

---

### 6.2 ChannelController 修改

**文件位置**: `laravel/app/Admin/Controllers/ChannelController.php`

**新增字段**: 在渠道编辑表单中添加User-Agent多选

```php
protected function form()
{
    return Form::make(Channel::with('allowedUserAgents'), function (Form $form) {
        // ... 现有字段 ...

        // 新增：User-Agent限制配置
        $form->divider('User-Agent限制');
        $form->multipleSelect('allowedUserAgents', '允许的User-Agent')
            ->options(UserAgent::where('is_enabled', true)->pluck('name', 'id'))
            ->customFormat(function ($v) {
                return $v ? array_column($v, 'id') : [];
            })
            ->saving(function ($value) {
                return $value;
            })
            ->help('选择允许访问此渠道的User-Agent，留空表示允许所有');

        // ... 其他字段 ...
    });
}
```

---

### 6.3 路由配置

**文件位置**: `laravel/app/Admin/routes.php`

```php
// User-Agent规则管理路由
$router->resource('user-agents', UserAgentController::class);
```

---

### 6.4 菜单配置

在 `laravel/database/admin_menu.json` 中添加菜单项:

```json
{
    "parent_id": 0,
    "order": 15,
    "title": "User-Agent规则",
    "icon": "fa-user-secret",
    "uri": "user-agents",
    "show": 1
}
```

---

## 7. 数据库迁移

### 7.1 创建迁移文件

**文件位置**: `laravel/database/migrations/2026_03_17_150000_create_user_agents_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 创建user_agents表
        Schema::create('user_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('规则名称');
            $table->json('patterns')->comment('正则表达式数组');
            $table->text('description')->nullable()->comment('规则描述');
            $table->boolean('is_enabled')->default(true)->comment('是否启用');
            $table->unsignedBigInteger('hit_count')->default(0)->comment('命中次数');
            $table->timestamp('last_hit_at')->nullable()->comment('最后命中时间');
            $table->timestamps();

            $table->index('is_enabled', 'idx_enabled');
        });

        // 创建channel_user_agent中间表
        Schema::create('channel_user_agent', function (Blueprint $table) {
            $table->unsignedBigInteger('channel_id')->comment('渠道ID');
            $table->unsignedBigInteger('user_agent_id')->comment('User-Agent ID');
            $table->timestamps();

            $table->primary(['channel_id', 'user_agent_id']);
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('user_agent_id')->references('id')->on('user_agents')->onDelete('cascade');

            $table->index('channel_id', 'idx_channel_id');
            $table->index('user_agent_id', 'idx_user_agent_id');
        });

        // 修改channels表，添加标志字段
        Schema::table('channels', function (Blueprint $table) {
            $table->boolean('has_user_agent_restriction')->default(false)->comment('是否有UA限制');
            $table->index('has_user_agent_restriction', 'idx_has_ua_restriction');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropIndex('idx_has_ua_restriction');
            $table->dropColumn('has_user_agent_restriction');
        });

        Schema::dropIfExists('channel_user_agent');
        Schema::dropIfExists('user_agents');
    }
};
```

---

## 8. 测试设计

### 8.1 单元测试

#### UserAgentTest

**文件位置**: `laravel/tests/Unit/Models/UserAgentTest.php`

```php
<?php

namespace Tests\Unit\Models;

use App\Models\UserAgent;
use Tests\TestCase;

class UserAgentTest extends TestCase
{
    /** @test */
    public function it_can_match_user_agent_with_single_pattern()
    {
        $userAgent = UserAgent::factory()->create([
            'name' => 'Chrome',
            'patterns' => ['Chrome\/[0-9]+'],
            'is_enabled' => true,
        ]);

        $this->assertTrue($userAgent->matches('Mozilla/5.0 Chrome/120.0.0.0 Safari/537.36'));
        $this->assertFalse($userAgent->matches('Mozilla/5.0 Firefox/121.0'));
    }

    /** @test */
    public function it_can_match_user_agent_with_multiple_patterns()
    {
        $userAgent = UserAgent::factory()->create([
            'name' => 'Chrome',
            'patterns' => ['Chrome\/[0-9]+', 'CriOS\/[0-9]+', 'Mobile.*Chrome'],
            'is_enabled' => true,
        ]);

        // 匹配桌面Chrome
        $this->assertTrue($userAgent->matches('Mozilla/5.0 Chrome/120.0.0.0 Safari/537.36'));

        // 匹配移动Chrome (CriOS)
        $this->assertTrue($userAgent->matches('Mozilla/5.0 CriOS/120.0.6099.119 Mobile/15E148'));

        // 匹配移动Chrome (Mobile.*Chrome)
        $this->assertTrue($userAgent->matches('Mozilla/5.0 Mobile Chrome/120.0.0.0'));

        // 不匹配Firefox
        $this->assertFalse($userAgent->matches('Mozilla/5.0 Firefox/121.0'));
    }

    /** @test */
    public function it_does_not_match_when_disabled()
    {
        $userAgent = UserAgent::factory()->create([
            'patterns' => ['Chrome\/[0-9]+'],
            'is_enabled' => false,
        ]);

        $this->assertFalse($userAgent->matches('Mozilla/5.0 Chrome/120.0.0.0'));
    }

    /** @test */
    public function it_does_not_match_when_patterns_empty()
    {
        $userAgent = UserAgent::factory()->create([
            'patterns' => [],
            'is_enabled' => true,
        ]);

        $this->assertFalse($userAgent->matches('Mozilla/5.0 Chrome/120.0.0.0'));
    }

    /** @test */
    public function it_can_record_hit()
    {
        $userAgent = UserAgent::factory()->create(['hit_count' => 0]);

        $userAgent->recordHit();

        $this->assertEquals(1, $userAgent->hit_count);
        $this->assertNotNull($userAgent->last_hit_at);
    }

    /** @test */
    public function it_handles_invalid_regex_gracefully()
    {
        $userAgent = UserAgent::factory()->create([
            'patterns' => ['Invalid[Regex', 'Chrome\/[0-9]+'],
            'is_enabled' => true,
        ]);

        // 第一个正则无效，但第二个正则有效且匹配
        $this->assertTrue($userAgent->matches('Mozilla/5.0 Chrome/120.0.0.0'));

        // 两个正则都无效
        $invalidUserAgent = UserAgent::factory()->create([
            'patterns' => ['Invalid[Regex', 'Another[Invalid'],
            'is_enabled' => true,
        ]);

        // 应该返回false而不是抛出异常
        $this->assertFalse($invalidUserAgent->matches('Mozilla/5.0 Chrome/120.0.0.0'));
    }
}
```

---

#### ChannelUserAgentTest

**文件位置**: `laravel/tests/Unit/Models/ChannelUserAgentTest.php`

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Channel;
use App\Models\UserAgent;
use Tests\TestCase;

class ChannelUserAgentTest extends TestCase
{
    /** @test */
    public function it_allows_user_agent_when_matched()
    {
        $channel = Channel::factory()->create(['has_user_agent_restriction' => true]);
        $userAgent = UserAgent::factory()->create(['pattern' => 'Chrome\/[0-9]+']);

        $channel->allowedUserAgents()->attach($userAgent);

        $this->assertTrue($channel->isUserAgentAllowed('Mozilla/5.0 Chrome/120.0.0.0'));
        $this->assertFalse($channel->isUserAgentAllowed('Mozilla/5.0 Firefox/121.0'));
    }

    /** @test */
    public function it_allows_all_user_agents_when_no_restriction()
    {
        $channel = Channel::factory()->create(['has_user_agent_restriction' => false]);

        $this->assertTrue($channel->isUserAgentAllowed('Any User-Agent'));
    }

    /** @test */
    public function it_denies_when_restriction_enabled_but_no_patterns()
    {
        $channel = Channel::factory()->create(['has_user_agent_restriction' => true]);

        $this->assertFalse($channel->isUserAgentAllowed('Mozilla/5.0 Chrome/120.0.0.0'));
    }
}
```

---

### 8.2 功能测试

#### UserAgentFilterServiceTest

**文件位置**: `laravel/tests/Feature/Services/UserAgentFilterServiceTest.php`

```php
<?php

namespace Tests\Feature\Services;

use App\Models\Channel;
use App\Models\UserAgent;
use App\Services\Router\UserAgentFilterService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class UserAgentFilterServiceTest extends TestCase
{
    /** @test */
    public function it_filters_channels_by_user_agent()
    {
        // 创建渠道
        $channel1 = Channel::factory()->create(['has_user_agent_restriction' => true, 'name' => 'Channel 1']);
        $channel2 = Channel::factory()->create(['has_user_agent_restriction' => false, 'name' => 'Channel 2']);
        $channel3 = Channel::factory()->create(['has_user_agent_restriction' => true, 'name' => 'Channel 3']);

        // 创建User-Agent规则
        $chrome = UserAgent::factory()->create(['pattern' => 'Chrome\/[0-9]+']);

        // 关联规则
        $channel1->allowedUserAgents()->attach($chrome); // 只允许Chrome
        $channel3->allowedUserAgents()->attach($chrome); // 只允许Chrome

        // 测试过滤
        $service = app(UserAgentFilterService::class);
        $channels = collect([$channel1, $channel2, $channel3]);

        // Chrome请求：应该保留所有渠道
        $filtered = $service->filterChannels($channels, 'Mozilla/5.0 Chrome/120.0.0.0');
        $this->assertCount(3, $filtered);

        // Firefox请求：应该只保留channel2（无限制）
        $filtered = $service->filterChannels($channels, 'Mozilla/5.0 Firefox/121.0');
        $this->assertCount(1, $filtered);
        $this->assertEquals('Channel 2', $filtered->first()->name);
    }
}
```

---

### 8.3 集成测试

#### ChannelRoutingWithUserAgentTest

**文件位置**: `laravel/tests/Feature/ChannelRoutingWithUserAgentTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Channel;
use App\Models\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelRoutingWithUserAgentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_routes_to_different_channels_based_on_user_agent()
    {
        // 创建渠道
        $chromeChannel = Channel::factory()->create([
            'name' => 'Chrome Channel',
            'status' => 'active',
            'has_user_agent_restriction' => true,
        ]);
        $firefoxChannel = Channel::factory()->create([
            'name' => 'Firefox Channel',
            'status' => 'active',
            'has_user_agent_restriction' => true,
        ]);

        // 创建User-Agent规则
        $chrome = UserAgent::factory()->create(['pattern' => 'Chrome\/[0-9]+']);
        $firefox = UserAgent::factory()->create(['pattern' => 'Firefox\/[0-9]+']);

        // 关联规则
        $chromeChannel->allowedUserAgents()->attach($chrome);
        $firefoxChannel->allowedUserAgents()->attach($firefox);

        // 创建API Key
        $apiKey = ApiKey::factory()->create(['status' => 'active']);

        // Chrome请求应该路由到Chrome Channel
        $response = $this->postJson('/v1/chat/completions', [
            'model' => 'gpt-3.5-turbo',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ], [
            'Authorization' => 'Bearer ' . $apiKey->key,
            'User-Agent' => 'Mozilla/5.0 Chrome/120.0.0.0',
        ]);

        // 验证请求被处理（具体断言根据实际路由逻辑调整）
        // ...
    }
}
```

---

## 9. 性能优化

### 9.1 查询优化

#### 优化策略

1. **使用 `has_user_agent_restriction` 标志字段**
   - 快速筛选有限制的渠道，避免全表扫描
   - 索引: `idx_has_ua_restriction`

2. **预加载关联关系**
   ```php
   Channel::with('allowedUserAgents')->get();
   ```

3. **缓存User-Agent规则**
   ```php
   // 在UserAgent模型中添加缓存
   public static function getCachedEnabledRules()
   {
       return Cache::remember('user_agents:enabled', 3600, function () {
           return UserAgent::where('is_enabled', true)->get();
       });
   }
   ```

---

### 9.2 正则表达式优化

#### 优化建议

1. **避免过于复杂的正则表达式**
   - 拆分复杂规则为多条简单规则
   - 使用更具体的匹配模式

2. **正则表达式预编译**（PHP 7+ 已自动优化）

3. **监控正则匹配性能**
   ```php
   // 在UserAgent::matches()中添加性能日志
   $start = microtime(true);
   $result = preg_match($this->pattern, $userAgent);
   $duration = (microtime(true) - $start) * 1000;

   if ($duration > 10) { // 超过10ms记录
       Log::warning('User-Agent正则匹配耗时过长', [
           'pattern' => $this->pattern,
           'duration_ms' => $duration,
       ]);
   }
   ```

---

## 10. 运维与监控

### 10.1 日志记录

#### 关键日志点

1. **渠道跳过日志**
   ```php
   Log::info('渠道User-Agent不匹配，已跳过', [
       'channel_id' => $channel->id,
       'channel_name' => $channel->name,
       'user_agent' => $userAgent,
   ]);
   ```

2. **无可用渠道日志**
   ```php
   Log::warning('所有候选渠道均不匹配User-Agent', [
       'user_agent' => $userAgent,
       'candidate_count' => $candidateChannels->count(),
   ]);
   ```

3. **正则匹配错误日志**
   ```php
   Log::error('User-Agent正则匹配失败', [
       'pattern' => $this->pattern,
       'user_agent' => $userAgent,
       'error' => $e->getMessage(),
   ]);
   ```

---

### 10.2 监控指标

#### 建议监控项

1. **User-Agent命中率**
   - 通过 `user_agents.hit_count` 字段统计
   - 监控哪些规则最常用

2. **渠道跳过次数**
   - 在审计日志中记录跳过原因
   - 统计因User-Agent限制导致的渠道跳过率

3. **无可用渠道错误率**
   - 监控因User-Agent限制导致的请求失败

---

### 10.3 数据维护

#### 定期清理

```php
// 清理长期未使用的User-Agent规则（超过90天未命中）
UserAgent::where('last_hit_at', '<', now()->subDays(90))
    ->where('hit_count', 0)
    ->delete();

// 重置统计计数（可选）
UserAgent::query()->update(['hit_count' => 0, 'last_hit_at' => null]);
```

---

## 11. 安全考虑

### 11.1 正则表达式安全

#### 潜在风险

- **ReDoS（正则表达式拒绝服务攻击）**
  - 恶意构造的User-Agent可能触发灾难性回溯
  - 导致CPU占用过高，服务不可用

#### 防护措施

1. **正则表达式验证**
   ```php
   // 在UserAgent模型中添加验证规则
   protected static function boot()
   {
       parent::boot();

       static::saving(function ($model) {
           $patterns = $model->patterns ?? [];

           // 验证每个正则表达式有效性
           foreach ($patterns as $index => $pattern) {
               if (@preg_match($pattern, '') === false) {
                   throw new \InvalidArgumentException("第{$index}条正则表达式无效: {$pattern}");
               }

               // 检测危险模式（可选）
               if (preg_match('/[\*\+]{2,}/', $pattern)) {
                   throw new \InvalidArgumentException("第{$index}条正则表达式可能存在性能风险: {$pattern}");
               }
           }
       });
   }
   ```

2. **匹配超时限制**
   ```php
   // 在php.ini中设置
   pcre.backtrack_limit = 100000
   pcre.recursion_limit = 100000
   ```

3. **监控异常匹配耗时**
   ```php
   // 在UserAgent::matches()中添加性能日志
   foreach ($patterns as $pattern) {
       $start = microtime(true);
       $result = @preg_match($pattern, $userAgent);
       $duration = (microtime(true) - $start) * 1000;

       if ($duration > 10) { // 超过10ms记录
           Log::warning('User-Agent正则匹配耗时过长', [
               'pattern' => $pattern,
               'duration_ms' => $duration,
               'user_agent_length' => strlen($userAgent),
           ]);
       }

       if ($result) {
           return true;
       }
   }
   ```

---

### 11.2 访问控制

#### Admin权限管理

- 只有管理员可以创建/编辑User-Agent规则
- 敏感操作记录审计日志
- 支持规则的启用/禁用状态切换

---

## 12. 扩展性设计

### 12.1 未来扩展方向

#### 可能的功能扩展

1. **User-Agent分组**
   - 创建User-Agent分组（如"浏览器"、"爬虫"、"脚本工具"）
   - 批量关联渠道

2. **黑白名单模式**
   - 支持黑名单模式（禁止特定User-Agent）
   - 支持白名单模式（只允许特定User-Agent）

3. **条件组合**
   - 支持User-Agent与其他条件组合（如IP、时间段）
   - 更灵活的访问控制策略

4. **统计报表**
   - User-Agent访问分布统计
   - 渠道User-Agent匹配成功率
   - 图表可视化展示

---

### 12.2 扩展预留

#### 数据库设计预留

```sql
-- 未来可扩展字段
ALTER TABLE user_agents ADD COLUMN type VARCHAR(50) COMMENT '类型：browser/bot/script';
ALTER TABLE user_agents ADD COLUMN group_id BIGINT UNSIGNED COMMENT '分组ID';
ALTER TABLE user_agents ADD COLUMN mode ENUM('whitelist','blacklist') DEFAULT 'whitelist' COMMENT '模式';
```

---

## 13. 实施计划

### 13.1 开发任务清单

| 阶段 | 任务 | 预计工时 | 依赖 |
|------|------|----------|------|
| 1 | 创建数据库迁移文件 | 0.5h | - |
| 2 | 创建UserAgent模型 | 1h | 阶段1 |
| 3 | 修改Channel模型 | 1h | 阶段1 |
| 4 | 创建UserAgentFilterService | 2h | 阶段2,3 |
| 5 | 集成到ChannelSelector | 1h | 阶段4 |
| 6 | 创建UserAgentController | 3h | 阶段2 |
| 7 | 修改ChannelController | 1h | 阶段3 |
| 8 | 编写单元测试 | 2h | 阶段2-5 |
| 9 | 编写功能测试 | 2h | 阶段4-5 |
| 10 | 编写集成测试 | 2h | 阶段5-7 |
| 11 | 代码格式化与审查 | 1h | 阶段1-10 |
| 12 | 文档编写 | 1h | 阶段1-11 |
| **总计** | | **16.5h** | |

---

### 13.2 实施步骤

1. **数据库准备**
   - 创建迁移文件
   - 执行迁移：`php artisan migrate`

2. **Model开发**
   - 创建UserAgent模型
   - 修改Channel模型
   - 定义关联关系

3. **服务层开发**
   - 创建UserAgentFilterService
   - 集成到ChannelSelector

4. **Admin界面开发**
   - 创建UserAgentController
   - 修改ChannelController
   - 配置路由和菜单

5. **测试编写**
   - 单元测试
   - 功能测试
   - 集成测试

6. **代码质量**
   - 运行Pint格式化
   - 运行所有测试
   - 代码审查

---

## 14. 风险与应对

### 14.1 潜在风险

| 风险 | 影响 | 概率 | 应对措施 |
|------|------|------|----------|
| 正则表达式性能问题 | 高 | 中 | 添加验证、监控、超时限制 |
| 所有渠道被过滤导致请求失败 | 高 | 低 | 日志记录、告警、兜底策略 |
| Admin配置错误导致无法访问 | 中 | 低 | 权限控制、配置验证 |
| 与现有渠道亲和性冲突 | 中 | 低 | 明确优先级、充分测试 |

---

### 14.2 兜底策略

1. **紧急禁用**
   ```php
   // 在配置文件中添加开关
   'user_agent_filter_enabled' => env('USER_AGENT_FILTER_ENABLED', true),

   // UserAgentFilterService中检查
   if (!config('app.user_agent_filter_enabled')) {
       return $channels; // 功能关闭，不过滤
   }
   ```

2. **降级处理**
   - 当所有渠道被过滤时，返回友好的错误提示
   - 记录详细日志用于排查

---

## 15. 参考资料

### 15.1 相关文档

- [Laravel Eloquent 关联关系](https://laravel.com/docs/12.x/eloquent-relationships)
- [Dcat Admin 使用文档](https://dcatadmin.com/)
- [PHP PCRE 正则表达式文档](https://www.php.net/manual/zh/book.pcre.php)

### 15.2 相关代码

- `laravel/app/Models/Channel.php` - 渠道模型
- `laravel/app/Models/ChannelAffinityRule.php` - 渠道亲和性规则模型
- `laravel/app/Services/Router/ChannelSelector.php` - 渠道选择器
- `laravel/app/Services/ChannelAffinity/ChannelAffinityService.php` - 渠道亲和性服务

---

## 16. 附录

### 16.1 常用User-Agent正则表达式示例

#### 单条正则表达式示例

```php
// Chrome浏览器（桌面）
'Chrome\/[0-9]+'

// Firefox浏览器
'Firefox\/[0-9]+'

// Safari浏览器（不含Chrome）
'Safari\/[0-9]+(?!.*Chrome)'

// Edge浏览器
'Edg\/[0-9]+'

// Postman
'PostmanRuntime\/[0-9]+'

// Python requests
'python-requests\/[0-9]+'

// cURL
'^curl\/[0-9]+'

// Google Bot
'Googlebot\/[0-9]+'

// Bing Bot
'bingbot\/[0-9]+'
```

#### 多条正则表达式组合示例（推荐）

```php
// Chrome浏览器（含桌面和移动端）
[
    'Chrome\/[0-9]+',       // 桌面版Chrome
    'CriOS\/[0-9]+',        // iOS版Chrome
    'Mobile.*Chrome'        // Android移动版Chrome
]

// Firefox浏览器（含桌面和移动端）
[
    'Firefox\/[0-9]+',      // 桌面版Firefox
    'Fxios\/[0-9]+'         // iOS版Firefox
]

// Safari浏览器（含桌面和移动端）
[
    'Safari\/[0-9]+(?!.*Chrome)',  // 桌面版Safari（排除Chrome）
    'Mobile.*Safari\/[0-9]+'       // 移动版Safari
]

// Edge浏览器（含桌面和移动端）
[
    'Edg\/[0-9]+',          // Chromium版Edge
    'Edge\/[0-9]+'          // 旧版Edge
]

// Python脚本（含各种HTTP库）
[
    'python-requests\/[0-9]+',  // requests库
    'Python\/[0-9]+',           // urllib
    'urllib3\/[0-9]+'           // urllib3
]

// Node.js脚本
[
    'node-fetch',
    'axios\/[0-9]+',
    'got\/[0-9]+'
]

// 爬虫工具（常见爬虫集合）
[
    '(bot|crawler|spider|scraper)',  // 通用爬虫关键词
    'Googlebot\/[0-9]+',               // Google爬虫
    'bingbot\/[0-9]+',                 // Bing爬虫
    'Baiduspider\/[0-9]+',             // 百度爬虫
    'YandexBot\/[0-9]+',               // Yandex爬虫
    'facebookexternalhit'              // Facebook爬虫
]

// API测试工具
[
    'PostmanRuntime\/[0-9]+',  // Postman
    'Insomnia\/[0-9]+',        // Insomnia
    'HTTPie\/[0-9]+'           // HTTPie
]

// 浏览器开发者工具（调试用）
[
    'Mozilla\/[0-9]+.*Chrome.*Safari.*Edg'  // Edge DevTools
]
```

#### JSON格式示例（数据库存储）

```json
{
    "name": "Chrome浏览器",
    "patterns": [
        "Chrome\\/[0-9]+",
        "CriOS\\/[0-9]+",
        "Mobile.*Chrome"
    ],
    "description": "匹配Chrome浏览器（含桌面和移动端）",
    "is_enabled": true
}
```

---

**文档结束**