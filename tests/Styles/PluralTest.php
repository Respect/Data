<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Plural::class)]
class PluralTest extends TestCase
{
    private Plural $style;

    protected function setUp(): void
    {
        $this->style = new Plural();
    }

    /** @return array<int, array<int, string>> */
    public static function tableEntityProvider(): array
    {
        return [
            ['posts',              'Post'],
            ['comments',           'Comment'],
            ['categories',         'Category'],
            ['posts_categories',   'PostCategory'],
            ['posts_tags',         'PostTag'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function manyToManyTableProvider(): array
    {
        return [
            ['post',   'category', 'posts_categories'],
            ['user',   'group',    'users_groups'],
            ['group',  'profile',  'groups_profiles'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function columnsPropertyProvider(): array
    {
        return [
            ['id'],
            ['text'],
            ['name'],
            ['content'],
            ['created'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function foreignProvider(): array
    {
        return [
            ['posts',      'post_id'],
            ['authors',    'author_id'],
            ['tags',       'tag_id'],
            ['users',      'user_id'],
        ];
    }

    #[DataProvider('tableEntityProvider')]
    public function testTableAndEntitiesMethods(string $table, string $entity): void
    {
        $this->assertEquals($entity, $this->style->styledName($table));
        $this->assertEquals('id', $this->style->identifier($table));
    }

    #[DataProvider('columnsPropertyProvider')]
    public function testColumnsAndPropertiesMethods(string $column): void
    {
        $this->assertEquals($column, $this->style->styledProperty($column));
        $this->assertEquals($column, $this->style->realProperty($column));
        $this->assertFalse($this->style->isRemoteIdentifier($column));
    }

    #[DataProvider('manyToManyTableProvider')]
    public function testTableFromLeftRightTable(string $left, string $right, string $table): void
    {
        $this->assertEquals($table, $this->style->composed($left, $right));
    }

    #[DataProvider('foreignProvider')]
    public function testForeign(string $table, string $foreign): void
    {
        $this->assertTrue($this->style->isRemoteIdentifier($foreign));
        $this->assertEquals($foreign, $this->style->remoteIdentifier($table));
    }
}
