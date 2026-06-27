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

    /**
     * Scope name (PHP, singular camelCase) → entity class + DB table.
     *
     * @return array<int, array<int, string>>
     */
    public static function scopeEntityTableProvider(): array
    {
        return [
            ['post',          'Post',         'posts'],
            ['comment',       'Comment',      'comments'],
            ['category',      'Category',     'categories'],
            ['postCategory',  'PostCategory', 'posts_categories'],
            ['postTag',       'PostTag',      'posts_tags'],
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

    /**
     * Scope name (PHP, singular camelCase) → foreign key column.
     *
     * @return array<int, array<int, string>>
     */
    public static function foreignProvider(): array
    {
        return [
            ['post',      'post_id'],
            ['author',    'author_id'],
            ['tag',       'tag_id'],
            ['user',      'user_id'],
        ];
    }

    #[DataProvider('scopeEntityTableProvider')]
    public function testScopeResolvesToEntityClassAndTable(string $scope, string $entity, string $table): void
    {
        $this->assertEquals($entity, $this->style->styledName($scope));
        $this->assertEquals($table, $this->style->realName($scope));
        $this->assertEquals('id', $this->style->identifier($scope));
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
    public function testForeign(string $scope, string $foreign): void
    {
        $this->assertTrue($this->style->isRemoteIdentifier($foreign));
        $this->assertEquals($foreign, $this->style->remoteIdentifier($scope));
    }
}
