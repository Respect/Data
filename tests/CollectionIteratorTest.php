<?php

declare(strict_types=1);

namespace Respect\Data;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\Collections\Collection;

use function iterator_to_array;

#[CoversClass(CollectionIterator::class)]
class CollectionIteratorTest extends TestCase
{
    #[Test]
    public function staticBuilderShouldCreateRecursiveIterator(): void
    {
        $this->assertInstanceOf(
            'RecursiveIteratorIterator',
            CollectionIterator::recursive(Collection::foo()),
        );
    }

    #[Test]
    public function constructingShouldAcceptCollectionsOrArrays(): void
    {
        $iterator = new CollectionIterator(Collection::foo());
        $iterator2 = new CollectionIterator([Collection::foo()]);
        $this->assertEquals($iterator, $iterator2);
    }

    #[Test]
    public function keyShouldTrackNameCounts(): void
    {
        $i = new CollectionIterator(Collection::foo());
        $this->assertEquals('foo', $i->key());
        $this->assertEquals('foo2', $i->key());
        $this->assertEquals('foo3', $i->key());
    }

    #[Test]
    public function hasChildrenConsiderEmpties(): void
    {
        $coll = Collection::foo();
        $iterator = new CollectionIterator($coll);
        $this->assertFalse($iterator->hasChildren());
    }

    #[Test]
    public function hasChildrenUseCollectionChildren(): void
    {
        $coll = Collection::foo([Collection::bar()]);
        $iterator = new CollectionIterator($coll);
        $this->assertTrue($iterator->hasChildren());
    }

    #[Test]
    public function getChildrenConsiderEmpties(): void
    {
        $coll = Collection::foo();
        $iterator = new CollectionIterator($coll);
        $this->assertEquals(new CollectionIterator(), $iterator->getChildren());
    }

    #[Test]
    public function getChildrenUseCollectionWith(): void
    {
        $coll = Collection::foo([Collection::bar(), Collection::baz()]);
        $items = iterator_to_array(CollectionIterator::recursive($coll));
        $names = [];
        foreach ($items as $item) {
            $names[] = $item->name;
        }

        $this->assertContains('bar', $names);
        $this->assertContains('baz', $names);
    }

    #[Test]
    public function recursiveTraversalShouldVisitNestedChildren(): void
    {
        $coll = Collection::foo([Collection::bar([Collection::baz()])]);
        $items = iterator_to_array(CollectionIterator::recursive($coll));
        $this->assertCount(3, $items);
    }
}
