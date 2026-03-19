<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Typed;
use Respect\Data\EntityFactory;
use stdClass;

#[CoversClass(Nested::class)]
class NestedTest extends TestCase
{
    private Nested $hydrator;

    private EntityFactory $factory;

    protected function setUp(): void
    {
        $this->hydrator = new Nested();
        $this->factory = new EntityFactory();
    }

    #[Test]
    public function hydrateReturnsFalseForNonObject(): void
    {
        $collection = Collection::author();

        $this->assertFalse($this->hydrator->hydrate(null, $collection, $this->factory));
        $this->assertFalse($this->hydrator->hydrate(false, $collection, $this->factory));
        $this->assertFalse($this->hydrator->hydrate('string', $collection, $this->factory));
    }

    #[Test]
    public function hydrateSingleEntity(): void
    {
        $raw = new stdClass();
        $raw->id = 1;
        $raw->name = 'Author Name';
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
        $raw = new stdClass();
        $raw->id = 1;
        $raw->title = 'Post Title';
        $raw->author = new stdClass();
        $raw->author->id = 5;
        $raw->author->name = 'Author';

        $collection = Collection::post()->author;

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function hydrateWithMissingNestedKeyReturnsPartial(): void
    {
        $raw = new stdClass();
        $raw->id = 1;
        $raw->title = 'Post Title';
        // no 'author' key

        $collection = Collection::post()->author;

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function hydrateDeeplyNested(): void
    {
        $raw = new stdClass();
        $raw->id = 1;
        $raw->text = 'Comment';
        $raw->post = new stdClass();
        $raw->post->id = 10;
        $raw->post->title = 'Post';
        $raw->post->author = new stdClass();
        $raw->post->author->id = 100;
        $raw->post->author->name = 'Author';

        $collection = Collection::comment()->post->author;

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(3, $result);
    }

    #[Test]
    public function hydrateWithChildren(): void
    {
        $raw = new stdClass();
        $raw->id = 1;
        $raw->title = 'Post';
        $raw->author = new stdClass();
        $raw->author->id = 5;
        $raw->author->name = 'Author';
        $raw->category = new stdClass();
        $raw->category->id = 3;
        $raw->category->label = 'Tech';

        $collection = Collection::post(Collection::author(), Collection::category());

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(3, $result);
    }

    #[Test]
    public function hydrateWithTypedCollection(): void
    {
        $factory = new EntityFactory(entityNamespace: 'Respect\Data\Hydrators\\');
        $raw = new stdClass();
        $raw->id = 1;
        $raw->title = 'Issue';
        $raw->type = 'stdClass';

        $collection = Typed::by('type')->issue();

        $result = $this->hydrator->hydrate($raw, $collection, $factory);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function hydrateChildWithNullNameIsSkipped(): void
    {
        $raw = new stdClass();
        $raw->id = 1;

        $child = new Collection();
        $collection = Collection::post($child);

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function hydrateScalarNestedValueIsIgnored(): void
    {
        $raw = new stdClass();
        $raw->id = 1;
        $raw->author = 'not-an-object';

        $collection = Collection::post()->author;

        $result = $this->hydrator->hydrate($raw, $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }
}
