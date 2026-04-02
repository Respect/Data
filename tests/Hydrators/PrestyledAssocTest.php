<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Composite;
use Respect\Data\Collections\Typed;
use Respect\Data\EntityFactory;
use Respect\Data\Stubs\Author;
use Respect\Data\Stubs\Bug;

#[CoversClass(PrestyledAssoc::class)]
#[CoversClass(Base::class)]
class PrestyledAssocTest extends TestCase
{
    private EntityFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\');
    }

    #[Test]
    public function hydrateReturnsFalseForEmpty(): void
    {
        $hydrator = new PrestyledAssoc($this->factory);
        $coll = Collection::author();

        $this->assertFalse($hydrator->hydrateAll(null, $coll));
        $this->assertFalse($hydrator->hydrateAll([], $coll));
        $this->assertFalse($hydrator->hydrateAll(false, $coll));
    }

    #[Test]
    public function hydrateSingleEntity(): void
    {
        $hydrator = new PrestyledAssoc($this->factory);
        $collection = Collection::author();

        $result = $hydrator->hydrateAll(
            ['author__id' => 1, 'author__name' => 'Alice'],
            $collection,
        );

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
        $result->rewind();
        $entity = $result->current();
        $this->assertEquals(1, $this->factory->get($entity, 'id'));
        $this->assertEquals('Alice', $this->factory->get($entity, 'name'));
    }

    #[Test]
    public function hydrateMultipleEntitiesFromJoinedRow(): void
    {
        $hydrator = new PrestyledAssoc($this->factory);
        $collection = Collection::author([Collection::post()]);

        $result = $hydrator->hydrateAll(
            [
                'author__id' => 1,
                'author__name' => 'Alice',
                'post__id' => 10,
                'post__title' => 'Hello',
                'post__author' => 1,
            ],
            $collection,
        );

        $this->assertNotFalse($result);
        $this->assertCount(2, $result);

        $entities = [];
        foreach ($result as $entity) {
            $entities[] = $entity;
        }

        $this->assertEquals(1, $this->factory->get($entities[0], 'id'));
        $this->assertEquals('Alice', $this->factory->get($entities[0], 'name'));
        $this->assertEquals(10, $this->factory->get($entities[1], 'id'));
        $this->assertEquals('Hello', $this->factory->get($entities[1], 'title'));
    }

    #[Test]
    public function hydrateWiresRelationships(): void
    {
        $hydrator = new PrestyledAssoc($this->factory);
        $collection = Collection::post([Collection::author()]);

        $result = $hydrator->hydrateAll(
            [
                'post__id' => 10,
                'post__title' => 'Hello',
                'post__author' => 1,
                'author__id' => 1,
                'author__name' => 'Alice',
            ],
            $collection,
        );

        $this->assertNotFalse($result);
        $result->rewind();
        $post = $result->current();
        $author = $this->factory->get($post, 'author');
        $this->assertInstanceOf(Author::class, $author);
        $this->assertEquals(1, $this->factory->get($author, 'id'));
    }

    #[Test]
    public function hydrateReturnsRootRegardlessOfColumnOrder(): void
    {
        $hydrator = new PrestyledAssoc($this->factory);
        $collection = Collection::post([Collection::author()]);

        // Author columns appear before post columns
        $result = $hydrator->hydrate(
            [
                'author__id' => 1,
                'author__name' => 'Alice',
                'post__id' => 10,
                'post__title' => 'Hello',
                'post__author' => 1,
            ],
            $collection,
        );

        $this->assertNotFalse($result);
        $this->assertEquals(10, $this->factory->get($result, 'id'));
        $this->assertEquals('Hello', $this->factory->get($result, 'title'));
    }

    #[Test]
    public function hydrateResolvesTypedEntities(): void
    {
        $hydrator = new PrestyledAssoc($this->factory);
        $collection = Typed::issue('type');

        $result = $hydrator->hydrateAll(
            ['issue__id' => 1, 'issue__type' => 'Bug', 'issue__title' => 'Bug Report'],
            $collection,
        );

        $this->assertNotFalse($result);
        $result->rewind();
        $this->assertInstanceOf(Bug::class, $result->current());
    }

    #[Test]
    public function hydrateCompositeEntity(): void
    {
        $hydrator = new PrestyledAssoc($this->factory);
        $composite = Composite::author(['profile' => ['bio']], [Collection::post()]);

        $result = $hydrator->hydrateAll(
            [
                'author__id' => 1,
                'author__name' => 'Alice',
                'author_WITH_profile__bio' => 'A bio',
                'post__id' => 10,
                'post__title' => 'Hello',
            ],
            $composite,
        );

        $this->assertNotFalse($result);
        $this->assertCount(2, $result);
        $result->rewind();
        $entity = $result->current();
        $this->assertEquals(1, $this->factory->get($entity, 'id'));
        $this->assertEquals('A bio', $this->factory->get($entity, 'bio'));
    }

    #[Test]
    public function hydrateCachesCollMapAcrossRows(): void
    {
        $hydrator = new PrestyledAssoc($this->factory);
        $collection = Collection::author();

        $first = $hydrator->hydrateAll(
            ['author__id' => 1, 'author__name' => 'Alice'],
            $collection,
        );
        $second = $hydrator->hydrateAll(
            ['author__id' => 2, 'author__name' => 'Bob'],
            $collection,
        );

        $this->assertNotFalse($first);
        $this->assertNotFalse($second);
    }

    #[Test]
    public function hydrateThrowsOnUnknownPrefix(): void
    {
        $hydrator = new PrestyledAssoc($this->factory);
        $collection = Collection::author();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown column prefix');
        $hydrator->hydrateAll(
            ['author__id' => 1, 'unknown__foo' => 'bar'],
            $collection,
        );
    }
}
