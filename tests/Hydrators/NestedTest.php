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
class NestedTest extends TestCase
{
    private Nested $hydrator;

    private EntityFactory $factory;

    protected function setUp(): void
    {
        $this->hydrator = new Nested();
        $this->factory = new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\');
    }

    #[Test]
    public function hydrateReturnsFalseForNonArray(): void
    {
        $collection = Collection::author();

        $this->assertFalse($this->hydrator->hydrate(null, $collection, $this->factory));
        $this->assertFalse($this->hydrator->hydrate(false, $collection, $this->factory));
        $this->assertFalse($this->hydrator->hydrate('string', $collection, $this->factory));
    }

    #[Test]
    public function hydrateSingleEntity(): void
    {
        $raw = ['id' => 1, 'name' => 'Author Name'];
        $collection = Collection::author();

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

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
        $collection = Collection::post();
        $collection->stack(Collection::author());

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function hydrateWithMissingNestedKeyReturnsPartial(): void
    {
        $raw = ['id' => 1, 'title' => 'Post Title'];
        $collection = Collection::post();
        $collection->stack(Collection::author());

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

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
        $collection = Collection::comment();
        $post = Collection::post();
        $post->stack(Collection::author());
        $collection->stack($post);

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

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
        $collection = Collection::post($authorColl, $categoryColl);

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(3, $result);
    }

    #[Test]
    public function hydrateWithTypedCollection(): void
    {
        $raw = ['id' => 1, 'title' => 'Issue', 'type' => 'Bug'];
        $collection = Typed::issue('type');

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function hydrateChildWithNullNameIsSkipped(): void
    {
        $raw = ['id' => 1];
        $child = new Collection();
        $collection = Collection::post();
        $collection->addChild($child);

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function hydrateScalarNestedValueIsIgnored(): void
    {
        $raw = ['id' => 1, 'author' => 'not-an-array'];
        $collection = Collection::post();
        $collection->stack(Collection::author());

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }
}
