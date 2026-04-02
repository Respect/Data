<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Typed;
use Respect\Data\EntityFactory;

#[CoversClass(Nested::class)]
#[CoversClass(Base::class)]
class NestedTest extends TestCase
{
    private Nested $hydrator;

    private EntityFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\');
        $this->hydrator = new Nested($this->factory);
    }

    #[Test]
    public function hydrateReturnsFalseForNonArray(): void
    {
        $collection = Collection::author();

        $this->assertFalse($this->hydrator->hydrateAll(null, $collection));
        $this->assertFalse($this->hydrator->hydrateAll(false, $collection));
        $this->assertFalse($this->hydrator->hydrateAll('string', $collection));
    }

    #[Test]
    public function hydrateSingleEntity(): void
    {
        $raw = ['id' => 1, 'name' => 'Author Name'];
        $collection = Collection::author();

        $result = $this->hydrator->hydrateAll($raw, $collection);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
        $result->rewind();
        $entity = $result->current();
        $this->assertEquals(1, $this->factory->get($entity, 'id'));
        $this->assertEquals('Author Name', $this->factory->get($entity, 'name'));
        $this->assertSame($collection, $result[$entity]);
    }

    #[Test]
    public function hydrateWithNestedChild(): void
    {
        $raw = [
            'id' => 1,
            'title' => 'Post Title',
            'author' => ['id' => 5, 'name' => 'Author'],
        ];
        $collection = Collection::post([Collection::author()]);

        $result = $this->hydrator->hydrateAll($raw, $collection);

        $this->assertNotFalse($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function hydrateWithMissingNestedKeyReturnsPartial(): void
    {
        $raw = ['id' => 1, 'title' => 'Post Title'];
        $collection = Collection::post([Collection::author()]);

        $result = $this->hydrator->hydrateAll($raw, $collection);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function hydrateDeeplyNested(): void
    {
        $raw = [
            'id' => 1,
            'text' => 'Comment',
            'post' => [
                'id' => 10,
                'title' => 'Post',
                'author' => ['id' => 100, 'name' => 'Author'],
            ],
        ];
        $collection = Collection::comment([
            Collection::post([Collection::author()]),
        ]);

        $result = $this->hydrator->hydrateAll($raw, $collection);

        $this->assertNotFalse($result);
        $this->assertCount(3, $result);
    }

    #[Test]
    public function hydrateWithChildren(): void
    {
        $raw = [
            'id' => 1,
            'title' => 'Post',
            'author' => ['id' => 5, 'name' => 'Author'],
            'category' => ['id' => 3, 'label' => 'Tech'],
        ];
        $authorColl = Collection::author();
        $categoryColl = Collection::category();
        $collection = Collection::post([$authorColl, $categoryColl]);

        $result = $this->hydrator->hydrateAll($raw, $collection);

        $this->assertNotFalse($result);
        $this->assertCount(3, $result);
    }

    #[Test]
    public function hydrateWithTypedCollection(): void
    {
        $raw = ['id' => 1, 'title' => 'Issue', 'type' => 'Bug'];
        $collection = Typed::issue('type');

        $result = $this->hydrator->hydrateAll($raw, $collection);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function hydrateChildWithNullNameIsSkipped(): void
    {
        $raw = ['id' => 1];
        $child = new Collection();
        $collection = Collection::post([$child]);

        $result = $this->hydrator->hydrateAll($raw, $collection);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function hydrateScalarNestedValueIsIgnored(): void
    {
        $raw = ['id' => 1, 'author' => 'not-an-array'];
        $collection = Collection::post([Collection::author()]);

        $result = $this->hydrator->hydrateAll($raw, $collection);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function hydrateReturnsFalseForInvalidInput(): void
    {
        $this->assertFalse($this->hydrator->hydrate(null, Collection::author()));
    }

    #[Test]
    public function hydrateReturnsRootEntity(): void
    {
        $raw = ['id' => 1, 'name' => 'Alice'];
        $result = $this->hydrator->hydrate($raw, Collection::author());

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->factory->get($result, 'id'));
        $this->assertEquals('Alice', $this->factory->get($result, 'name'));
    }

    #[Test]
    public function hydrateReturnsRootWithWiredRelation(): void
    {
        $raw = [
            'id' => 1,
            'title' => 'Post',
            'author' => ['id' => 5, 'name' => 'Author'],
        ];
        $collection = Collection::post([Collection::author()]);

        $result = $this->hydrator->hydrate($raw, $collection);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->factory->get($result, 'id'));
        $author = $this->factory->get($result, 'author');
        $this->assertIsObject($author);
        $this->assertEquals(5, $this->factory->get($author, 'id'));
    }
}
