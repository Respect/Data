<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CakePHP::class)]
class CakePHPTest extends TestCase
{
    private CakePHP $style;

    protected function setUp(): void
    {
        $this->style = new CakePHP();
    }

    /** @return array<int, array<int, string>> */
    public static function tableEntityProvider(): array
    {
        return [
            ['posts',              'Post'],
            ['comments',           'Comment'],
            ['categories',         'Category'],
            ['post_categories',    'PostCategory'],
            ['post_tags',          'PostTag'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function manyToManyTableProvider(): array
    {
        return [
            ['post',   'category', 'post_categories'],
            ['user',   'group',    'user_groups'],
            ['group',  'profile',  'group_profiles'],
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
        $this->assertEquals($table, $this->style->realName($entity));
        $this->assertEquals('id', $this->style->identifier($table));
    }

    #[DataProvider('columnsPropertyProvider')]
    public function testColumnsAndPropertiesMethods(string $column): void
    {
        $this->assertEquals($column, $this->style->styledProperty($column));
        $this->assertEquals($column, $this->style->realProperty($column));
        $this->assertFalse($this->style->isRemoteIdentifier($column));
        $this->assertNull($this->style->remoteFromIdentifier($column));
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
        $this->assertEquals($table, $this->style->remoteFromIdentifier($foreign));
        $this->assertEquals($foreign, $this->style->remoteIdentifier($table));
    }
}
