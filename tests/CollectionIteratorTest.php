<?php

declare(strict_types=1);

namespace Respect\Data;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\Collections\Collection;

#[CoversClass(CollectionIterator::class)]
class CollectionIteratorTest extends TestCase
{
    #[Test]
    public function static_builder_should_create_recursive_iterator(): void
    {
        $this->assertInstanceOf(
            'RecursiveIteratorIterator',
            CollectionIterator::recursive(Collection::foo())
        );
    }

    #[Test]
    public function constructing_should_accept_collections_or_arrays(): void
    {
        $iterator = new CollectionIterator(Collection::foo());
        $iterator2 = new CollectionIterator(array(Collection::foo()));
        $this->assertEquals($iterator, $iterator2);
    }

    #[Test]
    public function key_should_track_nameCounts(): void
    {
        $i = new CollectionIterator(Collection::foo());
        $this->assertEquals('foo', $i->key());
        $this->assertEquals('foo2', $i->key());
        $this->assertEquals('foo3', $i->key());
    }

    #[Test]
    public function hasChildren_consider_empties(): void
    {
        $coll = Collection::foo();
        $iterator = new CollectionIterator($coll);
        $this->assertFalse($iterator->hasChildren());
    }

    #[Test]
    public function hasChildren_use_collection_children(): void
    {
        $coll = Collection::foo(Collection::bar());
        $iterator = new CollectionIterator($coll);
        $this->assertTrue($iterator->hasChildren());
    }

    #[Test]
    public function hasChildren_use_collection_next(): void
    {
        $coll = Collection::foo()->bar;
        $iterator = new CollectionIterator($coll);
        $this->assertTrue($iterator->hasChildren());
    }

    #[Test]
    public function getChildren_consider_empties(): void
    {
        $coll = Collection::foo();
        $iterator = new CollectionIterator($coll);
        $this->assertEquals(new CollectionIterator(), $iterator->getChildren());
    }

    #[Test]
    public function getChildren_use_collection_children(): void
    {
        $coll = Collection::foo(Collection::bar(), Collection::baz());
        list($foo_child, $bar_child) = $coll->getChildren();
        $items = iterator_to_array(CollectionIterator::recursive($coll));
        $this->assertContains($foo_child, $items);
        $this->assertContains($bar_child, $items);
    }

    #[Test]
    public function getChildren_use_collection_next(): void
    {
        $coll = Collection::foo()->bar;
        $iterator = new CollectionIterator($coll);
        $this->assertTrue($iterator->hasChildren());
    }
}
