<?php

namespace Respect\Data;

use Respect\Data\Collections\Collection;

class CollectionIteratorTest extends \PHPUnit_Framework_TestCase
{
    function test_static_builder_should_create_recursive_iterator()
    {
        $this->assertInstanceOf(
            'RecursiveIteratorIterator',
            CollectionIterator::recursive(Collection::foo())
        );
    }

    function test_constructing_should_accept_collections_or_arrays()
    {
        $iterator = new CollectionIterator(Collection::foo());
        $iterator2 = new CollectionIterator(array(Collection::foo()));
        $this->assertEquals($iterator, $iterator2);
    }

    function test_key_should_track_nameCounts()
    {
        $i = new CollectionIterator(Collection::foo());
        $this->assertEquals('foo', $i->key());
        $this->assertEquals('foo2', $i->key());
        $this->assertEquals('foo3', $i->key());
    }

    function test_hasChildren_consider_empties()
    {
        $coll = Collection::foo();
        $iterator = new CollectionIterator($coll);
        $this->assertFalse($iterator->hasChildren());
    }

    function test_hasChildren_use_collection_children()
    {
        $coll = Collection::foo(Collection::bar());
        $iterator = new CollectionIterator($coll);
        $this->assertTrue($iterator->hasChildren());
    }

    function test_hasChildren_use_collection_next()
    {
        $coll = Collection::foo()->bar;
        $iterator = new CollectionIterator($coll);
        $this->assertTrue($iterator->hasChildren());
    }

    function test_getChildren_consider_empties()
    {
        $coll = Collection::foo();
        $iterator = new CollectionIterator($coll);
        $this->assertEquals(new CollectionIterator(), $iterator->getChildren());
    }

    function test_getChildren_use_collection_children()
    {
        $coll = Collection::foo(Collection::bar(), Collection::baz());
        list($foo_child, $bar_child) = $coll->getChildren();
        $items = iterator_to_array(CollectionIterator::recursive($coll));
        $this->assertContains($foo_child, $items);
        $this->assertContains($bar_child, $items);
    }

    function test_getChildren_use_collection_next()
    {
        $coll = Collection::foo()->bar;
        $iterator = new CollectionIterator($coll);
        $this->assertTrue($iterator->hasChildren());
    }

}
