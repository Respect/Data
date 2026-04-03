<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Sakila;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrators\Nested;
use Respect\Data\InMemoryMapper;
use Respect\Data\Styles\Sakila;

#[CoversClass(Sakila::class)]
class SakilaIntegrationTest extends TestCase
{
    private Sakila $style;

    private InMemoryMapper $mapper;

    protected function setUp(): void
    {
        $this->style = new Sakila();
        $this->mapper = new InMemoryMapper(new Nested(new EntityFactory(
            style: $this->style,
            entityNamespace: __NAMESPACE__ . '\\',
        )));

        $this->mapper->seed('post', [
            ['post_id' => 5, 'title' => 'Post Title', 'text' => 'Post Text', 'author_id' => 1],
        ]);
        $this->mapper->seed('author', [
            ['author_id' => 1, 'name' => 'Author 1'],
        ]);
        $this->mapper->seed('comment', [
            ['comment_id' => 7, 'post_id' => 5, 'text' => 'Comment Text'],
            ['comment_id' => 8, 'post_id' => 4, 'text' => 'Comment Text 2'],
        ]);
        $this->mapper->seed('category', [
            ['category_id' => 2, 'name' => 'Sample Category', 'content' => null],
            ['category_id' => 3, 'name' => 'NONON', 'content' => null],
        ]);
        $this->mapper->seed('post_category', [
            ['post_category_id' => 66, 'post_id' => 5, 'category_id' => 2],
        ]);
    }

    #[Test]
    public function fetchAndPersistRoundTrip(): void
    {
        $entity = $this->mapper->fetch($this->mapper->post());
        $this->assertIsObject($entity);
        $this->assertEquals('Post Title', $this->mapper->entityFactory->get($entity, 'title'));
    }
}
