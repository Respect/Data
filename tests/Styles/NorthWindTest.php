<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(NorthWind::class)]
class NorthWindTest extends TestCase
{
    private NorthWind $style;

    protected function setUp(): void
    {
        $this->style = new NorthWind();
    }

    /** @return array<int, array<int, string>> */
    public static function tableEntityProvider(): array
    {
        return [
            ['Posts',              'Posts'],
            ['Comments',           'Comments'],
            ['Categories',         'Categories'],
            ['PostCategories',     'PostCategories'],
            ['PostTags',           'PostTags'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function manyToManyTableProvider(): array
    {
        return [
            ['Posts',  'Categories',   'PostCategories'],
            ['Users',  'Groups',       'UserGroups'],
            ['Groups', 'Profiles',     'GroupProfiles'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function columnsPropertyProvider(): array
    {
        return [
            ['Text'],
            ['Name'],
            ['Content'],
            ['Created'],
            ['Updated'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function keyProvider(): array
    {
        return [
            ['Posts',      'PostID'],
            ['Authors',    'AuthorID'],
            ['Tags',       'TagID'],
            ['Users',      'UserID'],
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
    public function testKeys(string $table, string $foreign): void
    {
        $this->assertTrue($this->style->isRemoteIdentifier($foreign));
        $this->assertEquals($table, $this->style->remoteFromIdentifier($foreign));
        $this->assertEquals($foreign, $this->style->identifier($table));
        $this->assertEquals($foreign, $this->style->remoteIdentifier($table));
    }
}
