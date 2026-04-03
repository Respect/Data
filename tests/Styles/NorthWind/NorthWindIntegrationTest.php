<?php

declare(strict_types=1);

namespace Respect\Data\Styles\NorthWind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrators\Nested;
use Respect\Data\InMemoryMapper;
use Respect\Data\Styles\NorthWind;

#[CoversClass(NorthWind::class)]
class NorthWindIntegrationTest extends TestCase
{
    private NorthWind $style;

    private InMemoryMapper $mapper;

    protected function setUp(): void
    {
        $this->style = new NorthWind();
        $this->mapper = new InMemoryMapper(new Nested(new EntityFactory(
            style: $this->style,
            entityNamespace: __NAMESPACE__ . '\\',
        )));

        $this->mapper->seed('Posts', [
            ['PostID' => 5, 'Title' => 'Post Title', 'Text' => 'Post Text', 'AuthorID' => 1],
        ]);
        $this->mapper->seed('Authors', [
            ['AuthorID' => 1, 'Name' => 'Author 1'],
        ]);
        $this->mapper->seed('Comments', [
            ['CommentID' => 7, 'PostID' => 5, 'Text' => 'Comment Text'],
            ['CommentID' => 8, 'PostID' => 4, 'Text' => 'Comment Text 2'],
        ]);
        $this->mapper->seed('Categories', [
            ['CategoryID' => 2, 'Name' => 'Sample Category', 'Description' => 'Category description'],
            ['CategoryID' => 3, 'Name' => 'NONON', 'Description' => null],
        ]);
        $this->mapper->seed('PostCategories', [
            ['PostCategoryID' => 66, 'PostID' => 5, 'CategoryID' => 2],
        ]);
    }

    #[Test]
    public function fetchAndPersistRoundTrip(): void
    {
        $entity = $this->mapper->fetch($this->mapper->Posts());
        $this->assertIsObject($entity);
        $this->assertEquals('Post Title', $this->mapper->entityFactory->get($entity, 'Title'));
    }
}
