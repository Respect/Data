<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Composite;
use Respect\Data\Collections\Filtered;
use Respect\Data\Collections\Typed;
use Respect\Data\EntityFactory;
use Respect\Data\Stubs\Bug;

#[CoversClass(Flat::class)]
class FlatTest extends TestCase
{
    private EntityFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\');
    }

    #[Test]
    public function hydrateReturnsFalseForEmpty(): void
    {
        $hydrator = $this->hydrator(['id']);
        $coll = Collection::author();

        $this->assertFalse($hydrator->hydrate(null, $coll, $this->factory));
        $this->assertFalse($hydrator->hydrate([], $coll, $this->factory));
        $this->assertFalse($hydrator->hydrate(false, $coll, $this->factory));
    }

    #[Test]
    public function hydrateReturnsFalseWhenNoEntitiesBuilt(): void
    {
        $hydrator = $this->hydrator([]);
        $filtered = Filtered::post();

        $this->assertFalse($hydrator->hydrate([1, 'value'], $filtered, $this->factory));
    }

    #[Test]
    public function hydrateSingleEntity(): void
    {
        $hydrator = $this->hydrator(['id', 'name']);
        $collection = Collection::author();

        $result = $hydrator->hydrate([1, 'Author'], $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
        $result->rewind();
        $entity = $result->current();
        $this->assertEquals(1, $this->factory->get($entity, 'id'));
        $this->assertEquals('Author', $this->factory->get($entity, 'name'));
    }

    #[Test]
    public function hydrateMultipleEntitiesWithPkBoundary(): void
    {
        $hydrator = $this->hydrator(['id', 'name', 'author_id', 'id', 'title']);
        $collection = Collection::author();
        $collection->stack(Collection::post());

        $result = $hydrator->hydrate([1, 'Author', 1, 10, 'Post Title'], $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(2, $result);

        $entities = [];
        foreach ($result as $entity) {
            $entities[] = $entity;
        }

        $this->assertEquals(1, $this->factory->get($entities[0], 'id'));
        $this->assertEquals('Author', $this->factory->get($entities[0], 'name'));
        $this->assertEquals(10, $this->factory->get($entities[1], 'id'));
        $this->assertEquals('Post Title', $this->factory->get($entities[1], 'title'));
    }

    #[Test]
    public function hydrateSkipsWiringForNullPkChild(): void
    {
        $hydrator = $this->hydrator(['id', 'text', 'post_id', 'id', 'title']);
        $collection = Collection::comment();
        $collection->stack(Collection::post());

        $result = $hydrator->hydrate([1, 'Hello', 5, null, null], $collection, $this->factory);

        $this->assertNotFalse($result);
        $result->rewind();
        $entity = $result->current();
        $this->assertEquals(1, $this->factory->get($entity, 'id'));
        $this->assertNull($this->factory->get($entity, 'post'));
    }

    #[Test]
    public function hydrateSkipsUnfilteredFilteredCollections(): void
    {
        $hydrator = $this->hydrator(['id', 'title']);
        $filtered = Filtered::post();
        // No filters set — Filtered without filters is skipped
        $collection = Collection::author();
        $collection->stack($filtered);

        $result = $hydrator->hydrate([1, 'Post Title'], $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function hydrateFilteredCollectionWithFilters(): void
    {
        $hydrator = $this->hydrator(['id', 'name', 'id']);
        $filtered = Filtered::author('name');
        $collection = Collection::post();
        $collection->stack($filtered);

        $result = $hydrator->hydrate([1, 'Author', 10], $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function hydrateResolvesTypedEntities(): void
    {
        $hydrator = $this->hydrator(['id', 'type', 'title']);
        $collection = Typed::issue('type');

        $result = $hydrator->hydrate([1, 'Bug', 'Bug Report'], $collection, $this->factory);

        $this->assertNotFalse($result);
        $result->rewind();
        $this->assertInstanceOf(Bug::class, $result->current());
    }

    #[Test]
    public function hydrateWithComposite(): void
    {
        $hydrator = $this->hydrator(['id', 'title', 'id', 'bio']);
        $composite = Composite::author(['profile' => ['bio']]);

        $result = $hydrator->hydrate([1, 'Author', 1, 'A bio'], $composite, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
        $result->rewind();
        $entity = $result->current();
        $this->assertEquals('A bio', $this->factory->get($entity, 'bio'));
    }

    /** @param list<string> $columnNames */
    private function hydrator(array $columnNames): Flat
    {
        return new class ($columnNames) extends Flat {
            /** @param list<string> $columnNames */
            public function __construct(
                private readonly array $columnNames,
            ) {
            }

            protected function resolveColumnName(mixed $reference, mixed $raw): string
            {
                /** @phpstan-ignore offsetAccess.invalidOffset */
                return $this->columnNames[$reference];
            }
        };
    }
}
