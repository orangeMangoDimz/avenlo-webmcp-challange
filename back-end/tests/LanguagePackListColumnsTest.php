<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/LanguagePack.php';

/**
 * 语言列表接口曾经用 SELECT * 返回全部语言包，把每个语言 50KB+ 的 translations
 * 一并传给前端，在高延迟链路上导致登录页长时间加载。列表查询必须只取展示用的元数据。
 */
class LanguagePackListColumnsTest extends TestCase
{
    private function model(): LanguagePack
    {
        // 该查询不需要数据库连接，绕过 BaseModel 的构造函数
        return (new ReflectionClass(LanguagePack::class))->newInstanceWithoutConstructor();
    }

    public function testListQueryNeverSelectsTranslations(): void
    {
        foreach ([false, true] as $enabledOnly) {
            $sql = $this->model()->buildLanguageListSql($enabledOnly);

            $this->assertStringNotContainsString('translations', $sql);
            $this->assertStringNotContainsString('*', $sql);
        }
    }

    public function testListQueryKeepsColumnsTheFrontendsRender(): void
    {
        $sql = $this->model()->buildLanguageListSql();

        foreach (['languageCode', 'languageName', 'flagEmoji', 'isEnabled', 'isDefault', 'isBuiltin'] as $column) {
            $this->assertStringContainsString($column, $sql);
        }
    }

    public function testEnabledOnlyFiltersAndOrdersByDefaultLanguage(): void
    {
        $this->assertStringContainsString('WHERE isEnabled = 1', $this->model()->buildLanguageListSql(true));
        $this->assertStringNotContainsString('WHERE', $this->model()->buildLanguageListSql(false));
        $this->assertStringEndsWith('ORDER BY isDefault DESC, languageName ASC', $this->model()->buildLanguageListSql(true));
    }
}
