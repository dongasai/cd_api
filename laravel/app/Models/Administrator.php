<?php

namespace App\Models;

use Dcat\Admin\Models\Administrator as BaseAdministrator;

/**
 * 自定义管理员模型
 *
 * 扩展 dcat-admin 的默认模型，添加语言设置支持
 */
class Administrator extends BaseAdministrator
{
    /**
     * 可填充的属性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'password',
        'name',
        'avatar',
        'language',
    ];

    /**
     * 获取用户的界面语言设置
     *
     * @return string 返回语言代码，如 'zh_CN', 'en'
     */
    public function getLanguage(): string
    {
        return $this->language ?? 'zh_CN';
    }

    /**
     * 设置用户的界面语言
     *
     * @param  string  $language  语言代码
     */
    public function setLanguage(string $language): bool
    {
        return $this->update(['language' => $language]);
    }

    /**
     * 支持的语言列表
     *
     * @return array<string, string>
     */
    public static function getSupportedLanguages(): array
    {
        return [
            'zh_CN' => '简体中文',
            'en' => 'English',
        ];
    }
}
