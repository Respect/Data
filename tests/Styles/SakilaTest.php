<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sakila::class)]
class SakilaTest extends TestCase
{
    private Sakila $style;

    protected function setUp(): void
    {
        $this->style = new Sakila();
    }

    /** @return array<int, array<int, string>> */
    public static function tableEntityProvider(): array
    {
        return [
            ['post',           'Post'],
            ['comment',        'Comment'],
            ['category',       'Category'],
            ['post_category',  'PostCategory'],
            ['post_tag',       'PostTag'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function manyToManyTableProvider(): array
    {
        return [
            ['post',   'category', 'post_category'],
            ['user',   'group',    'user_group'],
            ['group',  'profile',  'group_profile'],
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
    public static function keyProvider(): array
    {
        return [
            ['post',       'post_id'],
            ['author',     'author_id'],
            ['tag',        'tag_id'],
            ['user',       'user_id'],
        ];
    }

    #[DataProvider('tableEntityProvider')]
    public function testTableAndEntitiesMethods(string $table, string $entity): void
    {
        $this->assertEquals($entity, $this->style->styledName($table));
        $this->assertEquals($table, $this->style->realName($entity));
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

    #[DataProvider('keyProvider')]
    public function testForeign(string $table, string $key): void
    {
        $this->assertTrue($this->style->isRemoteIdentifier($key));
        $this->assertEquals($table, $this->style->remoteFromIdentifier($key));
        $this->assertEquals($key, $this->style->identifier($table));
        $this->assertEquals($key, $this->style->remoteIdentifier($table));
    }
}
